<?php
namespace App\Http\Controllers;

use App\Models\Cliente;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AuthController extends Controller
{
    // Inicio de sesión
    public function login(Request $request)
    {
        $credentials = $request->only('correo', 'contrasena');
        $user = Cliente::where('correo', $credentials['correo'])->first();
        if ($user && Hash::check($credentials['contrasena'], $user->contrasena)) {
            $token = JWTAuth::fromUser($user);
             return response()->json([
            'success' => true,        
            'token' => $token,
            'user' => $user           
        ], 200);
        }

        return response()->json([
        'success' => false,           
        'message' => 'Credenciales inválidas'
    ], 401);
    }

    // Cerrar sesión
    public function logout()
    {
        JWTAuth::invalidate(JWTAuth::getToken());
        return response()->json(['message' => 'Sesión cerrada correctamente']);
    }


}
