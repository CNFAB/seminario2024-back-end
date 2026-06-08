<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Ingreso_d;

class Presupuesto extends Model
{
    protected $table = 'presupuesto';
    protected $primaryKey = 'id_presupuesto';

    public $timestamps = false;

    protected $fillable = [
        'id_ingreso',
        'id_usuario',        // ← agregar este campo
        'estado',
        'total_estimado',
        'fecha_validez'
    ];

    // Relaciones
    public function detalles()
    {
        return $this->hasMany(PresupuestoDetalle::class, 'id_presupuesto');
    }

    public function ingreso()
    {
    return $this->belongsTo(Ingreso_d::class, 'id_ingreso', 'id_ingreso'); // ← agregar tercer parámetro
    }

    // Relación con el usuario que creó el presupuesto
    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario');
    }

    // Constantes para estados
    const ESTADO_PENDIENTE = 'PENDIENTE';
    const ESTADO_APROBADO = 'APROBADO';
    const ESTADO_RECHAZADO = 'RECHAZADO';
    const ESTADO_CALCULADO = 'CALCULADO';

    // Métodos útiles
    public function recalcularTotal()
    {
        $this->total_estimado = $this->detalles()->sum('costo');
        $this->save();
        return $this->total_estimado;
    }

    public function aprobar()
    {
        $this->estado = self::ESTADO_APROBADO;
        $this->save();
    }

    public function rechazar()
    {
        $this->estado = self::ESTADO_RECHAZADO;
        $this->save();
    }
       public function calcular()
    {
        $this->estado = self::ESTADO_CALCULADO;
        $this->save();
    }
}