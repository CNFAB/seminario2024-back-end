<?php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Contracts\JWTSubject; // ← AGREGAR

class Usuario extends Authenticatable implements JWTSubject // ← AGREGAR implements
{
    
    protected $table = 'usuario';
    protected $primaryKey = 'id_usuario';
    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'apellido',
        'es_tecnico',
        'es_recepcionista',
        'es_administrador',
        'correo',
        'numero_celular',
        'contrasena',
        'activo',
        'en_linea'
    ];

    protected $hidden = [
        'contrasena'
    ];

    // ← AGREGAR ESTOS DOS MÉTODOS OBLIGATORIOS PARA JWT
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    // Todo lo demás queda igual...
    public function setContrasenaAttribute($value)
    {
        $this->attributes['contrasena'] = Hash::make($value);
    }

    public function diagnosticos()
    {
        return $this->hasMany(Diagnostico::class, 'id_usuario', 'id_usuario');
    }
    public function reparaciones()
{
    return $this->hasMany(Reparacion::class, 'id_usuario', 'id_usuario');
}

    public function getRolAttribute()
    {
        if ($this->es_tecnico) return 'tecnico';
        if ($this->es_recepcionista) return 'recepcionista';
        if ($this->es_administrador) return 'administrador';
        return 'cliente';
    }

   public function verificarContrasena($password)
{
    return Hash::check($password, $this->attributes['contrasena']);
}
    
    public function scopeActivos($query)
    {
    return $query->where('activo', true);
    }
    public function scopeInactivos($query)
    {
    return $query->where('activo', false);
    }
    public function isActivo(): bool
    {
    return $this->activo;
    }
    public function scopeTecnicos($query)
    {
        return $query->where('es_tecnico', true);
    }

    public function scopeRecepcionistas($query)
    {
        return $query->where('es_recepcionista', true);
    }

    public function scopeAdministradores($query)
    {
        return $query->where('es_administrador', true);
    }

    public function getNombreCompletoAttribute()
    {
        return $this->nombre . ' ' . $this->apellido;
    }
}