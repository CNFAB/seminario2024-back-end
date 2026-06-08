<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class UsuarioAuthController extends Controller
{
    /**
     * Login de usuarios internos
     */
    public function login(Request $request)
    {
        $request->validate([
            'correo' => 'required|email',
            'contrasena' => 'required|string'
        ]);

        try {
            $usuario = Usuario::where('correo', $request->correo)->first();

            if (!$usuario || !$usuario->verificarContrasena($request->contrasena)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Credenciales incorrectas'
                ], 401);
            }

            if (!$usuario->es_tecnico && !$usuario->es_recepcionista && !$usuario->es_administrador) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no tiene permisos de acceso'
                ], 403);
            }

            // Activar usuario al iniciar sesión
            $usuario->en_linea = true;
            $usuario->save();

            $token = JWTAuth::fromUser($usuario);

            \Log::info('Usuario logueado - en_linea = true', [
                'id_usuario' => $usuario->id_usuario,
                'correo' => $usuario->correo
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Login exitoso',
                'token' => $token,
                'data' => [
                    'id_usuario' => $usuario->id_usuario,
                    'nombre' => $usuario->nombre,
                    'apellido' => $usuario->apellido,
                    'correo' => $usuario->correo,
                    'en_linea' => $usuario->en_linea,
                    'roles' => [
                        'es_tecnico' => (bool) $usuario->es_tecnico,
                        'es_recepcionista' => (bool) $usuario->es_recepcionista,
                        'es_administrador' => (bool) $usuario->es_administrador
                    ]
                ]
            ]);

        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el token'
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error en el servidor',
                'error' => $e->getMessage()
            ], 500);
        }
    } // ← CIERRE DE login()

    /**
     * Obtener información del usuario autenticado
     */
    public function me()
    {
        try {
            $usuario = JWTAuth::parseToken()->authenticate();
            
            if (!$usuario) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id_usuario' => $usuario->id_usuario,
                    'nombre' => $usuario->nombre,
                    'apellido' => $usuario->apellido,
                    'correo' => $usuario->correo,
                    'en_linea' => $usuario->en_linea,
                    'roles' => [
                        'es_tecnico' => (bool) $usuario->es_tecnico,
                        'es_recepcionista' => (bool) $usuario->es_recepcionista,
                        'es_administrador' => (bool) $usuario->es_administrador
                    ]
                ]
            ]);

        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token inválido o expirado'
            ], 401);
        }
    } // ← CIERRE DE me()

    /**
     * Cerrar sesión
     */
   public function logout()
{
    try {
        //  Obtener el token de la petición
        $token = JWTAuth::getToken();
        
        if ($token) {
            //  Obtener el payload del token (sin validar expiración)
            $payload = JWTAuth::getPayload($token);
            $userId = $payload->get('sub');
            
            if ($userId) {
                $usuario = Usuario::find($userId);
                if ($usuario) {
                    $usuario->en_linea = false;
                    $usuario->save();
                    
                    \Log::info('Usuario deslogueado - en_linea = false', [
                        'id_usuario' => $usuario->id_usuario,
                        'correo' => $usuario->correo
                    ]);
                }
            }
            
            // Invalidar el token
            JWTAuth::invalidate($token);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Sesión cerrada correctamente'
        ]);

    } catch (\Exception $e) {
        \Log::error('Error en logout', ['error' => $e->getMessage()]);
        
        return response()->json([
            'success' => false,
            'message' => 'Error al cerrar sesión'
        ], 500);
    }
}
    /**
     * Refrescar token
     */
    public function refresh()
    {
        try {
            $newToken = JWTAuth::refresh(JWTAuth::getToken());
            
            return response()->json([
                'success' => true,
                'token' => $newToken
            ]);

        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al refrescar el token'
            ], 500);
        }
    } // ← CIERRE DE refresh()

} // ← CIERRE DE LA CLASE