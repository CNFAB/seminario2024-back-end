<?php

namespace App\Http\Controllers;

use App\Models\PrecioReparacion;
use Illuminate\Http\Request;

class PrecioReparacionController extends Controller
{
    public function index(){
        //mostramos todos los registros de los diagnosticos
        $precio_re = PrecioReparacion::all(); 
        return $precio_re;
    }
    public function store(Request $request)
    {

    //   creamos una nueva lista de los Diagnosticos 
         $validated = $request->validated();
        
        $precio_re = new PrecioReparacion() ;
        $precio_re->fill($validated);
        $precio_re->save();
        return $precio_re;
    }
    public function show(string $id ){
        $precio_re = PrecioReparacion ::find($id);
        return $precio_re;
    }
}
