<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Contracts\Auth\CanResetPassword;  
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;

class Cliente extends Authenticatable implements JWTSubject,  CanResetPassword 
{
    use HasFactory, Notifiable;

    protected $table = 'cliente';
    protected $primaryKey = 'id_cliente';
    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'apellido', 
        'numero_celular',
        'correo',
        'contrasena'
    ];

    // Campos ocultos en respuestas JSON
    protected $hidden = [
        'contrasena',
        'remember_token',
    ];

    // Casts para tipos de datos
    protected $casts = [
        'fecha_registro' => 'datetime', // Si tienes este campo
    ];
    
       public function getEmailForPasswordReset()
    {
        return $this->correo;
    }
//     public function getAuthIdentifierName()
// {
//     return 'correo';  // ← Usa 'correo' como identificador
// }
    
    /**
     * Send the password reset notification.
     */
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new \App\Notifications\ClienteResetPasswordNotification($token));
    }

  
     // ============================================
    // MÉTODO AGREGADO PARA NOTIFICACIONES
    // ============================================
    public function routeNotificationForMail()
    {
        return $this->correo;
    }
    // ============================================

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }
        public function esCliente()
{
    return true; 
}

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     */
   public function getJWTCustomClaims(): array
{
    return [
        'tipo' => 'cliente',
        'prv' => hash('sha256', \App\Models\Cliente::class), // ← Asegura que use la clase completa
    ];
}

    /**
     * Hash the password before saving
     */
    public function setContrasenaAttribute($value): void
    {
        if (!empty($value)) {
            $this->attributes['contrasena'] = Hash::make($value);
        }
    }

    /**
     * Relación con dispositivos
     */
    public function dispositivos(): HasMany
    {
        return $this->hasMany(Dispositivo::class, 'id_cliente');
    }

    /**
     * Get the name of the unique identifier for the user.
     */
    public function username(): string
    {
        return 'correo';
    }

    /**
     * Scope para buscar clientes por nombre o apellido
     */
    public function scopeBuscar($query, $termino)
    {
        return $query->where('nombre', 'LIKE', "%{$termino}%")
                    ->orWhere('apellido', 'LIKE', "%{$termino}%")
                    ->orWhere('correo', 'LIKE', "%{$termino}%");
    }

    /**
     * Scope para clientes activos (si agregas campo estado)
     */
    public function scopeActivos($query)
    {
        return $query->where('estado', 'activo'); // Si agregas campo estado
    }

    /**
     * Get full name attribute
     */
    public function getNombreCompletoAttribute(): string
    {
        return "{$this->nombre} {$this->apellido}";
    }
}