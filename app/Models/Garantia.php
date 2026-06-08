<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Garantia extends Model
{
    use HasFactory;

    protected $table = 'garantia';
    protected $primaryKey = 'id_garantia';
    
    // ✅ Deshabilitar timestamps si no los usas o si solo tienes created_at
    public $timestamps = false;
    
    protected $fillable = [
        'id_reparacion',
        'fecha_inicio',
        'fecha_fin',
        'duracion_meses',
        'duracion_dias',
        'tipo_garantia',
        'estado',
        'comentario',
        'created_by'
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'duracion_meses' => 'integer',
        'duracion_dias' => 'integer',
    ];

    // Constantes de estados
    const ESTADO_ACTIVA = 'ACTIVA';
    const ESTADO_VENCIDA = 'VENCIDA';
    const ESTADO_ANULADA = 'ANULADA';
    const ESTADO_RECLAMADA = 'RECLAMADA';
    const ESTADO_CUMPLIDA = 'CUMPLIDA';

    // Constantes de tipos
    const TIPO_ESTANDAR = 'ESTANDAR';
    const TIPO_EXTENDIDA = 'EXTENDIDA';
    const TIPO_PREMIUM = 'PREMIUM';
    const TIPO_CORTESIA = 'CORTESIA';

    // ==========================================
    // RELACIONES
    // ==========================================

    public function garantiaPiezas()
    {
        return $this->hasMany(GarantiaPieza::class, 'id_garantia', 'id_garantia');
    }

    public function reparacion(): BelongsTo
    {
        return $this->belongsTo(Reparacion::class, 'id_reparacion', 'id_reparacion');
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeActivas($query)
    {
        return $query->where('estado', self::ESTADO_ACTIVA)
                     ->where('fecha_fin', '>=', now());
    }

    public function scopeVencidas($query)
    {
        return $query->where('estado', self::ESTADO_VENCIDA)
                     ->orWhere('fecha_fin', '<', now());
    }

    // ==========================================
    // ACCESORS
    // ==========================================

    public function getDiasRestantesAttribute()
    {
        if ($this->fecha_fin < now()) {
            return 0;
        }
        return now()->diffInDays($this->fecha_fin);
    }

    public function isActiva()
    {
        return $this->estado === self::ESTADO_ACTIVA && $this->fecha_fin >= now();
    }
}