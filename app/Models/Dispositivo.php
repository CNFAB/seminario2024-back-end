<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dispositivo extends Model
{
    protected $table = 'dispositivo';
    protected $primaryKey = 'id_dispositivo';
    public $timestamps = false;

    protected $fillable= [
        'id_cliente',
        'id_modelo',
        'imei'
    ];


    public function cliente(){
        return $this->belongsTo(Cliente:: class,'id_cliente');
         
    }   
}
