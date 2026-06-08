<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GarantiaPieza extends Model
{
    use HasFactory;

    protected $table = 'garantia_pieza';
    protected $primaryKey = 'id_garantia_pieza';
    
    // ✅ Solo tiene created_at, no updated_at
    const UPDATED_AT = null;
    
    protected $fillable = [
        'id_garantia',
        'id_reparacion_multiple',
        'id_pieza',
        'id_categoria',
        'garantia_dias_asignados',
        'fecha_inicio',
        'fecha_fin',
        'estado',
        'comentario'
    ];

    protected $casts = [
        'garantia_dias_asignados' => 'integer',
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'created_at' => 'datetime',
    ];

    // Estados posibles
    const ESTADO_ACTIVA = 'ACTIVA';
    const ESTADO_VENCIDA = 'VENCIDA';
    const ESTADO_RECLAMADA = 'RECLAMADA';
    const ESTADO_CUMPLIDA = 'CUMPLIDA';

    // ==========================================
    // RELACIONES
    // ==========================================

    public function garantia()
    {
        return $this->belongsTo(Garantia::class, 'id_garantia', 'id_garantia');
    }

    public function reparacionMultiple()
    {
        return $this->belongsTo(ReparacionMultiple::class, 'id_reparacion_multiple', 'id_multiple');
    }

    public function pieza()
    {
        return $this->belongsTo(Pieza::class, 'id_pieza', 'id_pieza');
    }

    public function categoria()
    {
        return $this->belongsTo(Categoria::class, 'id_categoria', 'id_categoria');
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

    public function scopePorGarantia($query, $idGarantia)
    {
        return $query->where('id_garantia', $idGarantia);
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