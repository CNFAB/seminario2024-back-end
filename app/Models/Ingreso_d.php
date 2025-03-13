<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ingreso_d extends Model
{
    protected $table = 'ingreso_d';
    protected $primaryKey = 'id_ingreso';
    public $timestamps = false;
    
    protected $fillable =[
        'id_dispositivo',
        'id_usuario',
        'memoria_sd',
        'sim',
        'fecha_ingreso',
        'estado_del_ingreso',
        'comentario_del_cliente',
        'revisio_tecnica'
    ] ;
}
