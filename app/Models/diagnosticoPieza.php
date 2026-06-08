<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiagnosticoPieza extends Model
{
    use HasFactory;
    
    protected $table = 'diagnostico_pieza';
    protected $primaryKey = 'id_diagnostico_pieza';
    public $timestamps = false;
    
    protected $fillable = [
        'id_diagnostico',
        'id_pieza',
        'estado',
        'costo',
        'fecha_aprobacion',
        'fecha_rechazo',
        'creacion',
        'comentario'
    ];
    
    protected $casts = [
        'costo' => 'decimal:2',
        'creacion' => 'datetime',
        'fecha_aprobacion' => 'date',
        'fecha_rechazo' => 'date'
    ];
    
    // Relación con Pieza
    public function pieza(): BelongsTo
    {
        return $this->belongsTo(Pieza::class, 'id_pieza', 'id_pieza');
    }
    
    // Relación con Diagnostico
    public function diagnostico(): BelongsTo
    {
        return $this->belongsTo(Diagnostico::class, 'id_diagnostico', 'id_diagnostico');
    }
    
    // Método auxiliar para obtener el costo formateado
    public function getCostoFormateadoAttribute()
    {
        return '$' . number_format($this->costo, 2);
    }
    
    // Método auxiliar para saber si tiene comentario personalizado
    public function getTieneComentarioAttribute()
    {
        return $this->comentario && $this->comentario !== 'Sin comentario';
    }
}