<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReparacionMultiple extends Model
{
    use HasFactory;

    protected $table = 'reparacion_multiple';
    protected $primaryKey = 'id_multiple';
    public $timestamps = false;

    protected $fillable = [
        'id_reparacion',
        'id_pieza',
        'fecha_ini_reparacion',
        'fecha_fin_reparacion',
        'estado',
         'estado_pago',    
        'comentario_tecnico',
        'precio_pieza_momento', 
        'mano_obra_momento',   
        'precio_total',
         'created_at',   
        'updated_at'
    ];

    protected $casts = [
        'fecha_ini_reparacion' => 'datetime',
        'fecha_fin_reparacion' => 'datetime',
        'estado' => 'string',
        'precio_total' => 'decimal:2'
    ];

    // Estados específicos para el trabajo del técnico
const ESTADOS_TECNICO = [
    'ESPERANDO_DIAGNOSTICO' => 'ESPERANDO_DIAGNOSTICO',
    'ESPERANDO_APROBACION'  => 'ESPERANDO_APROBACION',
    'NO_REPARADO'           => 'NO_REPARADO',
    'EN_REPARACION'         => 'EN_REPARACION',
    'ESPERANDO_PIEZA'       => 'ESPERANDO_PIEZA',
    'LISTO_PARA_RETIRAR'    => 'LISTO_PARA_RETIRAR',
    'APROBADO'              => 'APROBADO',
    'RECHAZADO'             => 'RECHAZADO',
    'CANCELADO'             => 'CANCELADO',
    'EN_REVISION'           => 'EN_REVISION',
    'PAGADO'                => 'PAGADO',
    'PENDIENTE'             => 'PENDIENTE',
    'TERMINADO'             => 'TERMINADO',
];

    protected $appends = [
        'duracion_horas',
        'precio_final',
        'mano_obra',
        'subtotal',
        'resumen'
    ];

    // ==================== RELACIONES ====================

    public function reparacion()
    {
        return $this->belongsTo(Reparacion::class, 'id_reparacion', 'id_reparacion');
    }

    public function pieza()
    {
        return $this->belongsTo(Pieza::class, 'id_pieza', 'id_pieza');
    }
    public function getClienteAttribute()
    {
    return $this->reparacion?->diagnostico?->ingreso?->dispositivo?->cliente;
    }

    // ==================== SCOPES ====================

    public function scopePendientes($query)
    {
        return $query->where('estado', 'PENDIENTE');
    }

    public function scopeEnReparacion($query)
    {
        return $query->where('estado', 'EN_REPARACION');
    }

    public function scopeTerminadas($query)
    {
        return $query->where('estado', 'TERMINADO');
    }

    public function scopeEsperandoPieza($query)
    {
        return $query->where('estado', 'ESPERANDO_PIEZA');
    }

    public function scopeCanceladas($query)
    {
        return $query->where('estado', 'CANCELADO');
    }

    // ==================== VERIFICACIONES DE ESTADO ====================

    public function isPendiente(): bool
    {
        return $this->estado === 'PENDIENTE';
    }

    public function isEnReparacion(): bool
    {
        return $this->estado === 'EN_REPARACION';
    }

    public function isTerminado(): bool
    {
        return $this->estado === 'TERMINADO';
    }

    public function isEsperandoPieza(): bool
    {
        return $this->estado === 'ESPERANDO_PIEZA';
    }

    public function isCancelado(): bool
    {
        return $this->estado === 'CANCELADO';
    }

    // ==================== ACCIONES ====================

    public function comenzarReparacion($comentario = null): bool
    {
        return $this->update([
            'estado' => 'EN_REPARACION',
            'fecha_ini_reparacion' => $this->fecha_ini_reparacion ?? now(),
            'comentario_tecnico' => $comentario
        ]);
    }

    public function terminarReparacion($comentario = null): bool
    {
        // Si no hay precio_total, calcularlo antes de terminar
        if (!$this->precio_total) {
            $this->calcularPrecioTotal();
        }

        return $this->update([
            'estado' => 'TERMINADO',
            'fecha_fin_reparacion' => now(),
            'comentario_tecnico' => $comentario
        ]);
    }

    public function marcarComoEsperandoPieza($comentario = null): bool
    {
        return $this->update([
            'estado' => 'ESPERANDO_PIEZA',
            'comentario_tecnico' => $comentario
        ]);
    }

    public function cancelarReparacion($comentario = null): bool
    {
        return $this->update([
            'estado' => 'CANCELADO',
            'fecha_fin_reparacion' => now(),
            'comentario_tecnico' => $comentario
        ]);
    }

    // ==================== CÁLCULOS AUTOMÁTICOS ====================

    /**
     * Calcular el precio_total basado en pieza + mano de obra
     */
    public function calcularPrecioTotal(): float
    {
        if (!$this->id_pieza) {
            $this->precio_total = 0;
            return 0;
        }

        // Cargar la pieza con su categoría si no está cargada
        if (!$this->relationLoaded('pieza')) {
            $this->load(['pieza.categoria']);
        }

        $precioPieza = $this->pieza->precio ?? 0;
        $manoObraPorcentaje = $this->pieza->categoria->mano_obra ?? 0;
        
        $precioFinal = $precioPieza + ($precioPieza * ($manoObraPorcentaje / 100));
        
        $this->precio_total = round($precioFinal, 2);
        return $this->precio_total;
    }

    /**
     * Get duration in hours.
     */
    public function getDuracionHorasAttribute()
    {
        if (!$this->fecha_ini_reparacion) {
            return null;
        }

        $fin = $this->fecha_fin_reparacion ?? now();
        return $this->fecha_ini_reparacion->diffInHours($fin);
    }

    /**
     * Calculate the subtotal (precio de la pieza)
     */
    public function getSubtotalAttribute(): float
    {
        if ($this->precio_total) {
            // Si ya hay precio_total, calcular subtotal basado en mano de obra
            $manoObraPorcentaje = $this->mano_obra_porcentaje;
            if ($manoObraPorcentaje > 0) {
                return round($this->precio_total / (1 + ($manoObraPorcentaje / 100)), 2);
            }
            return (float) $this->precio_total;
        }
        
        return (float) ($this->pieza->precio ?? 0);
    }

    /**
     * Calculate the mano de obra amount
     */
    public function getManoObraAttribute(): float
    {
        if ($this->precio_total) {
            // Si ya hay precio_total, calcular mano de obra
            $subtotal = $this->subtotal;
            return round($this->precio_total - $subtotal, 2);
        }
        
        // Calcular basado en pieza y categoría
        $precioPieza = $this->pieza->precio ?? 0;
        $manoObraPorcentaje = $this->mano_obra_porcentaje;
        return round($precioPieza * ($manoObraPorcentaje / 100), 2);
    }

    /**
     * Calculate the final price (subtotal + mano de obra)
     */
    public function getPrecioFinalAttribute(): float
    {
        if ($this->precio_total) {
            return (float) $this->precio_total;
        }
        
        return round($this->subtotal + $this->mano_obra, 2);
    }

    /**
     * Get percentage of mano de obra
     */
    public function getManoObraPorcentajeAttribute(): float
    {
        if (!$this->relationLoaded('pieza')) {
            $this->load(['pieza.categoria']);
        }
        
        return (float) ($this->pieza->categoria->mano_obra ?? 0);
    }

    /**
     * Check if the repair is active (not finished or canceled)
     */
    public function getEstaActivaAttribute(): bool
    {
        return in_array($this->estado, ['PENDIENTE', 'EN_REPARACION', 'ESPERANDO_PIEZA']);
    }

    /**
     * Get summary of the repair
     */
    public function getResumenAttribute(): array
    {
        return [
            'id_multiple' => $this->id_multiple,
            'id_reparacion' => $this->id_reparacion,
            'id_pieza' => $this->id_pieza,
            'estado' => $this->estado,
            'fecha_inicio' => $this->fecha_ini_reparacion?->format('Y-m-d H:i:s'),
            'fecha_fin' => $this->fecha_fin_reparacion?->format('Y-m-d H:i:s'),
            'duracion_horas' => $this->duracion_horas,
            'comentario_tecnico' => $this->comentario_tecnico,
            'subtotal' => $this->subtotal,
            'mano_obra' => $this->mano_obra,
            'mano_obra_porcentaje' => $this->mano_obra_porcentaje,
            'precio_final' => $this->precio_final,
            'precio_total' => $this->precio_total,
            'esta_activa' => $this->esta_activa,
            'pieza_nombre' => $this->pieza->nombre_pieza ?? null,
            'pieza_precio' => $this->pieza->precio ?? null,
            'categoria_nombre' => $this->pieza->categoria->categoria ?? null,
        ];
    }

    // ==================== BOOT METHOD ====================

    /**
     * Boot method to set precio_total automatically
     */


    /**
     * Override save method to ensure precio_total is calculated
     */
    
}