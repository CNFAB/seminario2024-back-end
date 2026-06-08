<?php

namespace App\Http\Controllers;

use App\Http\Requests\Cliente\StoreClienteRequest;
use App\Http\Requests\Cliente\LoginClienteRequest;
use App\Models\Cliente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class JWTAuthController extends Controller
{
    public function register(StoreClienteRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $cliente = Cliente::create($validated);

            // ✅ Usar guard 'cliente'
            $token = auth('cliente')->login($cliente);

            return response()->json([
                'success' => true,
                'message' => 'Cliente registrado exitosamente',
                'data' => [
                    'cliente' => $cliente,
                    'token' => $token,
                    'token_type' => 'bearer',
                    'expires_in' => config('jwt.ttl') * 60
                ]
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error en el registro',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function login(LoginClienteRequest $request): JsonResponse
    {
        try {
            $credentials = $request->validated();

            $cliente = Cliente::where('correo', $credentials['correo'])->first();

            if (!$cliente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Credenciales incorrectas'
                ], Response::HTTP_UNAUTHORIZED);
            }

            if (!Hash::check($credentials['contrasena'], $cliente->contrasena)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Credenciales incorrectas'
                ], Response::HTTP_UNAUTHORIZED);
            }

            // ✅ Usar guard 'cliente'
            $token = auth('cliente')->login($cliente);
 $parts = explode('.', $token);
        $payload = json_decode(base64_decode($parts[1]), true);
        \Log::info('=== LOGIN DEBUG ===');
        \Log::info('PRV generado: ' . $payload['prv']);
        \Log::info('PRV esperado: ' . hash('sha256', \App\Models\Cliente::class));

            return response()->json([
                'success' => true,
                'message' => 'Login exitoso',
                'data' => [
                    'cliente' => $cliente,
                    'token' => $token,
                    'token_type' => 'bearer',
                    'expires_in' => config('jwt.ttl') * 60
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error en el login',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getUser(): JsonResponse
    {
        try {
            // ✅ Usar guard 'cliente'
            $cliente = auth('cliente')->user();

            if (!$cliente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cliente no encontrado'
                ], Response::HTTP_NOT_FOUND);
            }

            return response()->json([
                'success' => true,
                'data' => $cliente->load('dispositivos')
            ]);

        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token inválido'
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    public function logout(): JsonResponse
    {
        try {
            // ✅ Usar guard 'cliente'
            auth('cliente')->logout();

            return response()->json([
                'success' => true,
                'message' => 'Logout exitoso'
            ]);

        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al hacer logout'
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    public function refresh(): JsonResponse
    {
        try {
            // ✅ Usar guard 'cliente'
            $newToken = auth('cliente')->refresh();

            return response()->json([
                'success' => true,
                'data' => [
                    'token' => $newToken,
                    'token_type' => 'bearer',
                    'expires_in' => config('jwt.ttl') * 60
                ]
            ]);

        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo refrescar el token'
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}