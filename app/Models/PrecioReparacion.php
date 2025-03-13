<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrecioReparacion extends Model
{
    //
    protected $table = 'precio_reparacion';
    protected $primaryKey = 'id_precio';
    public $timestamps = false;
}

