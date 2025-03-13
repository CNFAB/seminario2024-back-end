<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;//autenticacion de los hashs
use  Illuminate\Foundation\Auth\User as Authenticatable;

   

class Cliente extends Authenticatable  implements JWTSubject
{

    use HasFactory, Notifiable;


    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    //
    protected $table = 'cliente';
    protected $primaryKey = 'id_cliente';
    public $timestamps = false;

    //protected $table ='diagnostico';
    
    // Agregar los campos que se pueden asignar masivamente
    protected $fillable = [
        'nombre',
        'apellido',
        'numero_celular',
        'correo',
        'contrasena'
        // Agrega aquí el campo numero_celular
        // otros campos que desees permitir
    ];
    public function dispositivos(){
        return $this->hasMany(Dispositivo:: class,'id_cliente');
         
    } 
    public function username(){
        return 'correo';
    }

//     protected function descripcion():Attribute
//     {
//         return Attribute::make (
//             get: fn(string $value) =>'*****'.strtoupper($value).'*****'
//             //formatea el codigo de envio dlsssdooo
//         )
//     }
}

