<?php
// app/Models/Pago.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pago extends Model
{
    protected $table      = 'pagos';
    protected $primaryKey = 'id_pago';
    public $timestamps    = false; // manejamos created_at/updated_at manualmente

    protected $fillable = [
        'id_reparacion',
        'mp_preference_id',
        'mp_payment_id',
        'estado',
        'monto',
        'descripcion',
        'fecha_pago',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'monto'      => 'decimal:2',
        'fecha_pago' => 'datetime',
    ];

    public function reparacion()
    {
        return $this->belongsTo(Reparacion::class, 'id_reparacion', 'id_reparacion');
    }
}