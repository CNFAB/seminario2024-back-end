<?php

namespace App\Http\Controllers;

use App\Models\Reparacion;
use Illuminate\Http\Request;

class ReparacionController extends Controller
{
    public function index(){
        //mostramos todos los registros de los diagnosticos
        $reparacion = Reparacion::all(); 
        return $reparacion;
    }
    public function store(Request $request)
    {

    //   creamos una nueva lista de los Diagnosticos 
         $validated = $request->validated();
        
        $reparacion = new Reparacion() ;
        $reparacion->fill($validated);
        $reparacion->save();
        return $reparacion;
    }
    public function show(string $id ){
        $reparacion = Reparacion ::find($id);
        return $reparacion;
    }
}
