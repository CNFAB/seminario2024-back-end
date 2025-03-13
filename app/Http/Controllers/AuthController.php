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
            return response()->json(['token' => $token], 200);
        }

        return response()->json(['error' => 'Credenciales inválidas'], 401);
    }

    // Obtener información del usuario autenticado
    // public function me()
    // {
    //     return response()->json(auth()->user());
    // }

    // Cerrar sesión
    public function logout()
    {
        JWTAuth::invalidate(JWTAuth::getToken());
        return response()->json(['message' => 'Sesión cerrada correctamente']);
    }


}
