<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Modelo extends Model
{
    protected $table = 'modelo';
    protected $primaryKey = 'id_modelo';
    public $timestamps = false;

    protected $fillable =[
        'id_marca',
        'nombre_modelo'
    ];
    public function marca(){
        return $this->belongsTo(Marca:: class,'id_marca');
         
    }   
}
