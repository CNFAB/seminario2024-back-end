<?php

return [

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
        'api' => [
            'driver' => 'jwt',
            'provider' => 'usuarios',  // ← Cambiado a 'usuarios'
        ],
        'usuario' => [
            'driver' => 'jwt',
            'provider' => 'usuarios',
        ],
        'cliente' => [
            'driver' => 'jwt',
            'provider' => 'clientes',
        ],
    ],

    'providers' => [
        // Proveedor para usuarios del sistema (admin, técnicos, recepcionistas)
        'usuarios' => [
            'driver' => 'eloquent',
            'model' => App\Models\Usuario::class,
        ],
        
        // Proveedor para clientes
        'clientes' => [                       
            'driver' => 'eloquent',
            'model' => App\Models\Cliente::class,
        ],
        
        // Provider genérico (si es necesario)
        'users' => [
            'driver' => 'eloquent',
            'model' => App\Models\Usuario::class,
        ],
    ],

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
        'clientes' => [
            'provider' => 'clientes',  // ← Cambiado de 'users' a 'clientes'
            'table' => 'cliente_password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
            'email' => 'correo',
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];