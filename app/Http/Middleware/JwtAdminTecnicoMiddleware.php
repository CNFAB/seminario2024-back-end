<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Exceptions\JWTException;

class JwtAdminTecnicoMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $user = auth('usuario')->setRequest($request)->user();


            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado o token inválido'
                ], 401);
            }

            if (!$user->es_administrador && !$user->es_tecnico) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autorizado. Se requieren permisos de administrador o técnico.'
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
                'message' => 'Error de autenticación: ' . $e->getMessage()
            ], 401);
        }

        return $next($request);
    }
}