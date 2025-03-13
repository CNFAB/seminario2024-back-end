<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMarcaRequest;
use App\Models\Marca;
use Illuminate\Http\Request;

class MarcaController extends Controller
{
    public function index(){
        //mostramos todos los registros de los diagnosticos
        $marca = Marca::all(); 
        return $marca;
    }
    public function store(StoreMarcaRequest $request)
    {

    //   creamos una nueva lista de los Diagnosticos 
         $validated = $request->validated();
        
        $marca = new Marca() ;
        $marca->fill($validated);
        $marca->save();
        return $marca;
    }
    public function show(string $id ){
        $marca = Marca ::find($id);
        return $marca;
    }

    
}
