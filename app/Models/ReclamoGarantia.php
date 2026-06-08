<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReclamoGarantia extends Model
{
    use HasFactory;

    protected $table = 'reclamos_garantia';
    protected $primaryKey = 'id_reclamo';

    protected $fillable = [
        'id_garantia',
        'id_garantia_pieza',
        'fecha_reclamo',
        'descripcion_problema',
        'fotos',
        'diagnostico_tecnico',
        'diagnostico_categoria',
        'estado',
        'prioridad',
        'fecha_resolucion',
        'comentario_tecnico',
        'id_nueva_reparacion',
        'monto_reclamado',
        'monto_aprobado',
        'id_tecnico_asignado'
    ];

    protected $casts = [
        'fecha_reclamo' => 'datetime',
        'fecha_resolucion' => 'datetime',
        'fotos' => 'array', 
        'monto_reclamado' => 'decimal:2',
        'monto_aprobado' => 'decimal:2'
    ];

    // Estados posibles
    const ESTADO_PENDIENTE = 'PENDIENTE';
    const ESTADO_EN_REVISION = 'EN_REVISION';
    const ESTADO_APROBADO = 'APROBADO';
    const ESTADO_RECHAZADO = 'RECHAZADO';
    const ESTADO_COMPLETADO = 'COMPLETADO';

    // Prioridades
    const PRIORIDAD_BAJA = 'BAJA';
    const PRIORIDAD_MEDIA = 'MEDIA';
    const PRIORIDAD_ALTA = 'ALTA';
    const PRIORIDAD_URGENTE = 'URGENTE';

    // Relaciones
    public function garantia()
    {
        return $this->belongsTo(Garantia::class, 'id_garantia', 'id_garantia');
    }

    public function garantiaPieza()
    {
        return $this->belongsTo(GarantiaPieza::class, 'id_garantia_pieza', 'id_garantia_pieza');
    }

    public function nuevaReparacion()
    {
        return $this->belongsTo(Reparacion::class, 'id_nueva_reparacion', 'id_reparacion');
    }

    // Scopes
    public function scopePendientes($query)
    {
        return $query->where('estado', self::ESTADO_PENDIENTE);
    }

    public function scopePorGarantia($query, $idGarantia)
    {
        return $query->where('id_garantia', $idGarantia);
    }
}