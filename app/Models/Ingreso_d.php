<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany; 

class Ingreso_d extends Model
{
    protected $table = 'ingreso_d';
    protected $primaryKey = 'id_ingreso';
    
    public $timestamps = true;
    const CREATED_AT = 'fecha_ingreso';
    const UPDATED_AT = null; // Si no tienes columna updated_at
    
    const ESTADO_TALLER = 'TALLER';
    const ESTADO_RETIRADO = 'RETIRADO';
    
    protected $fillable = [
        'id_dispositivo',
        'id_usuario',
        'memoria_sd',
        'sim',
        'estado_del_ingreso',
        'comentario_cliente',   
        'revision_tecnica',    
        'foto_frontal',
        'foto_trasera',
        'estado'
    ];

    protected $casts = [
        'memoria_sd' => 'boolean',
        'sim' => 'boolean',
        'fecha_ingreso' => 'datetime',
        'revision_tecnica' => 'boolean'
    ];

    // Mutadores para URLs de imágenes
    protected function fotoFrontal(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? asset('storage/' . $value) : null,
        );
    }

    protected function fotoTrasera(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? asset('storage/' . $value) : null,
        );
    }
    
    public function presupuestos()
    {
        return $this->hasMany(Presupuesto::class, 'id_ingreso');
    }

    // Relación con Dispositivo
    public function dispositivo(): BelongsTo
    {
        return $this->belongsTo(Dispositivo::class, 'id_dispositivo', 'id_dispositivo');
    }

    // Relación con Usuario
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }
    
    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'id_cliente', 'id_cliente');
    }

    // Relación con diagnósticos
    public function diagnosticos()
    {
        return $this->hasMany(Diagnostico::class, 'id_ingreso', 'id_ingreso');
    }
    
    // relacion con reparaciones
    // public function reparacion(): HasMany
    // {
    //     return $this->hasMany(Reparacion::class, 'id_ingreso', 'id_ingreso');
    // }
    public function reparacion()
{
    return $this->hasOne(Reparacion::class, 'id_ingreso', 'id_ingreso');
}
    
    public function getClienteAttribute()
    {
        return $this->dispositivo?->cliente;
    }
 
    // Scopes
    public function scopePendientesDiagnostico($query)
    {
        return $query->where('estado_del_ingreso', 'ESPERANDO DIAGNOSTICO');
    }

    public function scopeRevisionPendiente($query)
    {
        return $query->where('revision_tecnica', false);
    }

    public function scopePorDispositivo($query, $dispositivoId)
    {
        return $query->where('id_dispositivo', $dispositivoId);
    }

    public function scopePorUsuario($query, $usuarioId)
    {
        return $query->where('id_usuario', $usuarioId);
    }
    
    public function estaEnTaller()
    {
        return $this->estado === self::ESTADO_TALLER;
    }

    // Método para saber si está retirado
    public function estaRetirado()
    {
        return $this->estado === self::ESTADO_RETIRADO;
    }

    // Método para marcar como retirado
    public function marcarComoRetirado()
    {
        $this->estado = self::ESTADO_RETIRADO;
        return $this->save();
    }

    // Método para marcar como en taller
    public function marcarComoEnTaller()
    {
        $this->estado = self::ESTADO_TALLER;
        return $this->save();
    }

    // Scope para filtrar los que están en taller
    public function scopeEnTaller($query)
    {
        return $query->where('estado', self::ESTADO_TALLER);
    }

    // Scope para filtrar los que están retirados
    public function scopeRetirados($query)
    {
        return $query->where('estado', self::ESTADO_RETIRADO);
    }

    /**
     * Obtener el estado final del dispositivo para mostrar al cliente
     * 
     * @return string
     */
    public function getEstadoFinalAttribute()
    {
        // 1. Buscar diagnóstico asociado al ingreso
        $diagnostico = $this->diagnosticos()->first();
        
        if ($diagnostico) {
            // Si tiene diagnóstico, usar su estado
            if ($diagnostico->estado === 'LISTO_PARA_RETIRAR') {
                return 'LISTO_PARA_RETIRAR';
            }
            if ($diagnostico->estado === 'APROBADO') {
                // Si está aprobado pero no terminado
                $reparacion = $diagnostico->reparacion;
                if ($reparacion && $reparacion->estado_general === 'TERMINADO') {
                    return 'LISTO_PARA_RETIRAR';
                }
                return 'EN_REPARACION';
            }
            return $diagnostico->estado;
        }
        
        // 2. Buscar reparación directa (sin diagnóstico)
        $reparacionDirecta = $this->reparacion()->first();
        if ($reparacionDirecta) {
            $estadoReparacion = $reparacionDirecta->estado_general;
            if ($estadoReparacion === 'TERMINADO' || $estadoReparacion === 'COMPLETADO') {
                return 'TERMINADO';
            }
            return $estadoReparacion ?? 'PENDIENTE';
        }
        
        return 'PENDIENTE';
    }

    /**
     * Determinar si el dispositivo está listo para retirar
     */
    public function getListoParaRetirarAttribute(): bool
    {
        $estadoFinal = $this->estado_final;
        return in_array($estadoFinal, ['LISTO_PARA_RETIRAR', 'TERMINADO']);
    }

    /**
     * Scope para obtener ingresos listos para retirar
     */
    public function scopeListosParaRetirar($query)
    {
        return $query->where(function($q) {
            // Con diagnóstico listo
            $q->whereHas('diagnosticos', function($q2) {
                $q2->where('estado', 'LISTO_PARA_RETIRAR');
            })->orWhereHas('reparacion', function($q3) {
                // Reparación directa terminada
                $q3->where('estado', 'TERMINADO');
            });
        })->where('estado', '!=', 'retirado'); // No mostrar ya retirados
    }
}