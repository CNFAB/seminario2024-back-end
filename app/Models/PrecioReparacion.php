<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrecioReparacion extends Model
{
    use HasFactory;

   
    protected $table = 'precio_reparacion';
    protected $primaryKey = 'id_precio_reparacion';
    public $timestamps = false;

    protected $fillable = [
        'descripcion',
        'costo_de_reparacion',
        'id_categoria'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'costo_de_reparacion' => 'decimal:2',
    ];

    /**
     * Get the categoria that owns the precio reparacion.
     */
    public function categoria()
    {
        return $this->belongsTo(Categoria::class, 'id_categoria', 'id_categoria');
    }
}