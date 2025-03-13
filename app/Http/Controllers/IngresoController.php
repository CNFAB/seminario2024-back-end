<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreIngresoDRequest;
use App\Models\Dispositivo;
use App\Models\Ingreso_d;
use Illuminate\Auth\Events\Validated;

class IngresoController extends Controller
{
    public function index(){
        //mostramos todos los registros de los diagnosticos
        $ingreso = Ingreso_d::all(); 
        return $ingreso;
    }
    public function store(StoreIngresoDRequest $request)
    {
    //   creamos una nueva lista de los Diagnosticos 
         $validated = $request->validated();
       //lo dejamos para mas tarde lo de los ingresos 
        $ingreso = new Ingreso_d;
        $ingreso->fill($validated);
    }
    public function show(string $id ){
        $ingreso = Ingreso_d ::find($id);
        return $ingreso;
 
    }

}
