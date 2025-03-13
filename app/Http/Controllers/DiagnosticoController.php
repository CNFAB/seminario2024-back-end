<?php

namespace App\Http\Controllers;

use App\Models\Diagnostico;
use Dotenv\Util\Str;
use Illuminate\Http\Request;

class DiagnosticoController extends Controller
{
    public function index(){
        //mostramos todos los registros de los diagnosticos
        $diagnostico = Diagnostico::all(); 
        return $diagnostico;
    }
    public function store(Request $request)
    {

    //   creamos una nueva lista de los Diagnosticos 
         $validated = $request->validated();
        
        $diagnostico = new Diagnostico ;
        $diagnostico->fill($validated);
        $diagnostico->save();
        return $diagnostico;
    }
    public function show(string $id ){
        $diagnostico = Diagnostico ::find($id);
        return $diagnostico;
    }

}
