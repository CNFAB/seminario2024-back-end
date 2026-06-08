<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PresupuestoDetalle extends Model
{
    use HasFactory;
    
    protected $table = 'presupuesto_detalle';
    protected $primaryKey = 'id_detalle';
    
    public $timestamps = false;
    
    protected $fillable = [
        'id_presupuesto',
        'id_pieza',
        'descripcion',
        'costo',
        'aprobado'
    ];
    
    protected $casts = [
        'costo' => 'decimal:2',
        'aprobado' => 'boolean'
    ];
    
    // Relaciones
    public function presupuesto()
    {
        return $this->belongsTo(Presupuesto::class, 'id_presupuesto');
    }
    
    public function pieza()
    {
        return $this->belongsTo(Pieza::class, 'id_pieza');
    }
    
    // Scope para detalles aprobados
    public function scopeAprobados($query)
    {
        return $query->where('aprobado', true);
    }
    
    // Eventos para recalcular total del presupuesto
    protected static function booted()
    {
        static::saved(function ($detalle) {
            if ($detalle->presupuesto) {
                $detalle->presupuesto->recalcularTotal();
            }
        });
        
        static::deleted(function ($detalle) {
            if ($detalle->presupuesto) {
                $detalle->presupuesto->recalcularTotal();
            }
        });
    }
}