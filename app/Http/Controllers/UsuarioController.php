<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\Usuario;
class UsuarioController extends Controller
{
    public function index(){
       $usuario = Usuario::all();
       if(!$usuario){
        return response()->json(['error'=>'la base de usuarios esta vacia '],404);
      }
      return response()->json($usuario,200);
    }
    
    public function store(Request $request)
    {
        $usuario  = new Usuario;
        
        $usuario->nombre = $request->input('nombre');
        $usuario->apellido = $request ->input('apellido');
        $usuario->es_tecnico = $request ->input('es_tecnico');
        $usuario->es_recepcionista = $request-> input('es_recepcionista');
        $usuario->es_administrador =$request->input('es_administrador');

        $usuario->save();

        return $usuario;
    
    }


}