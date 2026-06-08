<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Testimonio extends Model
{
    use HasFactory;
    
    protected $table = 'testimonios';
    protected $primaryKey = 'id_testimonio';
    public $timestamps = true;
    
    protected $fillable = [
        'id_reparacion',
        'calificacion_estrella',
        'comentario',
        'estado',
        'skip_testimonio'
    ];
    
    protected $casts = [
        'calificacion_estrella' => 'integer',
        'skip_testimonio' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
    
    // ============================================
    // RELACIONES
    // ============================================
    
    /**
     * Un testimonio pertenece a una reparación
     */
    public function reparacion()
    {
        return $this->belongsTo(Reparacion::class, 'id_reparacion', 'id_reparacion');
    }
    
    /**
     * Obtener el cliente a través de la reparación
     */
    public function cliente()
    {
        return $this->hasOneThrough(
            Usuario::class,
            Reparacion::class,
            'id_reparacion',
            'id_usuario',
            'id_reparacion',
            'id_usuario'
        );
    }
    
    // ============================================
    // SCOPES (consultas comunes)
    // ============================================
    
    /**
     * Scope para testimonios aprobados (se muestran en la web)
     */
    public function scopeAprobados($query)
    {
        return $query->where('estado', 'APROBADO');
    }
    
    /**
     * Scope para testimonios pendientes de moderación
     */
    public function scopePendientes($query)
    {
        return $query->where('estado', 'PENDIENTE');
    }
    
    /**
     * Scope para testimonios rechazados
     */
    public function scopeRechazados($query)
    {
        return $query->where('estado', 'RECHAZADO');
    }
    
    /**
     * Scope para testimonios activos (aprobados y sin skip)
     */
    public function scopeActivos($query)
    {
        return $query->where('estado', 'APROBADO')->where('skip_testimonio', false);
    }
    
    // ============================================
    // ACCESORES (formatear datos)
    // ============================================
    
    /**
     * Formatear la fecha de creación
     */
    public function getFechaFormateadaAttribute()
    {
        return $this->created_at->format('d/m/Y');
    }
    
    /**
     * Obtener el nombre del cliente
     */
    public function getNombreClienteAttribute()
    {
        $reparacion = $this->reparacion;
        if ($reparacion && $reparacion->tecnico) {
            return $reparacion->tecnico->nombre . ' ' . $reparacion->tecnico->apellido;
        }
        return 'Cliente';
    }
    
    /**
     * Obtener el dispositivo reparado
     */
    public function getDispositivoAttribute()
    {
        $reparacion = $this->reparacion;
        if ($reparacion && $reparacion->ingreso && $reparacion->ingreso->dispositivo) {
            $dispositivo = $reparacion->ingreso->dispositivo;
            $marca = $dispositivo->modelo->marca->marca ?? '';
            $modelo = $dispositivo->modelo->nombre_modelo ?? '';
            return trim($marca . ' ' . $modelo);
        }
        return 'Dispositivo';
    }
}