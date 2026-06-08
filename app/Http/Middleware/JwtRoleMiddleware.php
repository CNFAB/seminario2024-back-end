// app/Http/Middleware/JwtRoleMiddleware.php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class JwtRoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        try {
            // Obtener el token y autenticar al usuario
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado'
                ], 404);
            }
            
            // Verificar si el rol del usuario está en los roles permitidos
            if (!in_array($user->rol, $roles)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autorizado. Se requieren los roles: ' . implode(', ', $roles)
                ], 403);
            }
            
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token inválido o expirado'
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de autenticación'
            ], 401);
        }
        
        return $next($request);
    }
}