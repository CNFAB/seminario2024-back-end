<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Marca extends Model
{
    protected $table = 'marca';
    protected $primaryKey = 'id_marca';
    public $timestamps = false;
    
    protected $fillable =[
        'marca'
    ];
    public function modelos(){
        return $this->hasMany(Modelo::class,'id_marca');//aqui hacemos la relacion de 1 a muchos 
    }
}
