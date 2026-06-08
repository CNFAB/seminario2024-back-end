<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class ClientePasswordController extends Controller
{
    public function forgotPassword(Request $request)
    {
        try {
            Log::info('🔍 [PASO 1] Iniciando forgotPassword');
            Log::info('📝 Datos recibidos:', $request->all());
            
            // Validar el correo
            $validator = validator($request->all(), [
                'correo' => 'required|email|exists:cliente,correo'
            ]);
            
            if ($validator->fails()) {
                Log::error('❌ Validación fallida:', $validator->errors()->toArray());
                return response()->json([
                    'success' => false,
                    'message' => 'Correo inválido o no registrado'
                ], 422);
            }
            
            Log::info('🔍 [PASO 2] Validación pasada', ['correo' => $request->correo]);

            // Buscar cliente
            $cliente = Cliente::where('correo', $request->correo)->first();
            
            if (!$cliente) {
                Log::error('❌ Cliente no encontrado', ['correo' => $request->correo]);
                return response()->json([
                    'success' => false,
                    'message' => 'No encontramos una cuenta con ese correo'
                ], 404);
            }
            
            Log::info('🔍 [PASO 3] Cliente encontrado', [
                'id' => $cliente->id_cliente,
                'nombre' => $cliente->nombre,
                'correo' => $cliente->correo
            ]);
            
            // Generar token
            $token = Str::random(64);
            Log::info('🔍 [PASO 4] Token generado', ['token' => $token]);
            
            // Guardar token en la tabla
            try {
                DB::table('cliente_password_reset_tokens')->updateOrInsert(
                    ['correo' => $cliente->correo],
                    [
                        'token' => $token,
                        'created_at' => now()
                    ]
                );
                Log::info('🔍 [PASO 5] Token guardado en BD');
            } catch (\Exception $dbError) {
                Log::error('❌ Error al guardar token en BD: ' . $dbError->getMessage());
                return response()->json([
                    'success' => false,
                    'message' => 'Error al guardar el token: ' . $dbError->getMessage()
                ], 500);
            }
            
            // Verificar que se guardó correctamente
            $savedToken = DB::table('cliente_password_reset_tokens')
                ->where('correo', $cliente->correo)
                ->first();
                
            Log::info('🔍 [PASO 6] Token guardado verificado', [
                'existe' => $savedToken ? 'si' : 'no',
                'token' => $savedToken ? $savedToken->token : null
            ]);
            
            // Devolver token al frontend
            return response()->json([
                'success' => true,
                'message' => 'Token generado correctamente',
                'data' => [
                    'token' => $token,
                    'correo' => $cliente->correo,
                    'nombre' => $cliente->nombre
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Error CRITICO en forgotPassword: ' . $e->getMessage());
            Log::error('❌ Archivo: ' . $e->getFile());
            Log::error('❌ Línea: ' . $e->getLine());
            Log::error('❌ Trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function resetPassword(Request $request)
    {
        try {
            Log::info('🔍 [RESET] Iniciando resetPassword');
            Log::info('📝 Datos recibidos:', $request->all());
            
            $request->validate([
                'correo' => 'required|email|exists:cliente,correo',
                'token' => 'required|string',
                'contrasena' => 'required|string|min:6|confirmed'
            ]);
            
            Log::info('🔍 [RESET] Validación pasada');

            // Buscar el token
            $resetRecord = DB::table('cliente_password_reset_tokens')
                ->where('correo', $request->correo)
                ->where('token', $request->token)
                ->first();
                
            Log::info('🔍 [RESET] Token buscado', [
                'correo' => $request->correo,
                'token' => $request->token,
                'encontrado' => $resetRecord ? 'si' : 'no'
            ]);

            if (!$resetRecord) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token inválido. Solicita un nuevo enlace.'
                ], 400);
            }

            // Verificar expiración (60 minutos)
            $createdAt = strtotime($resetRecord->created_at);
            $now = time();
            $diffMinutes = ($now - $createdAt) / 60;
            
            Log::info('🔍 [RESET] Verificación expiración', [
                'creado' => $resetRecord->created_at,
                'minutos_transcurridos' => round($diffMinutes, 2)
            ]);

            if ($diffMinutes > 60) {
                DB::table('cliente_password_reset_tokens')
                    ->where('correo', $request->correo)
                    ->delete();
                    
                return response()->json([
                    'success' => false,
                    'message' => 'El enlace ha expirado. Solicita uno nuevo.'
                ], 400);
            }

            // Actualizar contraseña
            $cliente = Cliente::where('correo', $request->correo)->first();
           $cliente->contrasena = $request->contrasena;
            $cliente->save();
            
            Log::info('✅ [RESET] Contraseña actualizada', ['cliente_id' => $cliente->id_cliente]);

            // Eliminar token usado
            DB::table('cliente_password_reset_tokens')
                ->where('correo', $request->correo)
                ->delete();

            return response()->json([
                'success' => true,
                'message' => 'Contraseña restablecida correctamente.'
            ]);

        } catch (\Exception $e) {
            Log::error('❌ [RESET] Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
}