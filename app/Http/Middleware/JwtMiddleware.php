<?php

namespace App\Http\Middleware;

use App\Models\Dispositivo;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class JwtMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            $dispositivo= Dispositivo ::where([
                'id_cliente'=> $user->id_cliente,
                'id_dispositivo'=> $user->id_dispositivo
            ])->first();
            // if ($user->correo == 'cata32@gmail.com') 
            if ($dispositivo!= null){
                return $next($request);
            } else {
                return response()->json(['error' => 'usuario no autorizado']);
            }
        } catch (JWTException $e) {
            return response()->json(['error' => 'token no es valido'], 401);
        }
    }
}
