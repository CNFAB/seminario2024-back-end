<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
// use Illuminate\Support\Facades\
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class JWTAuthController extends Controller
{

    // public function __construct(){
    //     $this->middleware('auth:api',['except'=>['login']]);
    // }

    // User registration
    public function register(Request $request)
    {
        // $validator = Validator::make($request->all(), [
        //     'nombre' => 'required|string|max:255',
        //     'email' => 'required|string|email|max:255|unique:users',
        //     'password' => 'required|string|min:6|confirmed',
        // ]);

        // if($validator->fails()){
        //     return response()->json($validator->errors()->toJson(), 400);
        // }
        $user = Cliente::create([
            'nombre' => $request->get('nombre'),
            'numero_celular' => $request->get('numero_celular'),
            'correo' => $request->get('correo'),
            'apellido'=>$request->get('apellido'),
            'contrasena' => Hash::make($request->get('contrasena')),
        ]);
        $token = JWTAuth::fromUser($user);

        return response()->json(compact('user','token'), 201);
    }
    // User login
    public function login(Request $request)
    {
        $credentials = $request->only('correo', 'contrasena');
        $cliente = Cliente ::where([
            // 'contrasena'=>$credentials[]
            'correo'=> $credentials['correo']
            ])->first();
            if(!Hash::check($credentials['contrasena'],$cliente->contrasena)){
            return ' no autorizado';
            }
            $token =JWTAUTH::fromUser($cliente);
            return $token;
    }
    public function getUser()
    {
        try {
            if (! $user = JWTAuth::parseToken()->authenticate()) {
                return response()->json(['error' => 'User not found'], 404);
            }
        } catch (JWTException $e) {
            return response()->json(['error' => 'Invalid token'], 400);
        }

        return response()->json(compact('user'));
    }

    // User logout
    public function logout()
    {
        JWTAuth::invalidate(JWTAuth::getToken());

        return response()->json(['message' => 'Successfully logged out']);
    }
}
