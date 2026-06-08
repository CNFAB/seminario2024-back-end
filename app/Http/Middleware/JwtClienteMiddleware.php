<?php

namespace App\Http\Middleware;

use Closure;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use App\Models\Cliente;
use Illuminate\Support\Facades\Log;

class JwtClienteMiddleware
{
    public function handle($request, Closure $next)
    {
        Log::info('=== NUEVO MIDDLEWARE CLIENTE ===');
        
        try {
            // Obtener el token manualmente
            $token = JWTAuth::parseToken();
            $payload = $token->getPayload();
            $clienteId = $payload->get('sub');
            
            Log::info('Cliente ID del token: ' . $clienteId);
            
            // Buscar el cliente manualmente
            $cliente = Cliente::find($clienteId);
            
            if (!$cliente) {
                Log::warning('Cliente no encontrado con ID: ' . $clienteId);
                return response()->json(['error' => 'Cliente no encontrado'], 401);
            }
            
            Log::info('Cliente autenticado: ' . $cliente->nombre);
            
            // Autenticar manualmente
            auth('cliente')->setUser($cliente);
            $request->attributes->set('cliente_autenticado', $cliente);
            
            return $next($request);
            
        } catch (JWTException $e) {
            Log::error('Error JWT: ' . $e->getMessage());
            return response()->json(['error' => 'Token inválido: ' . $e->getMessage()], 401);
        }
    }
}