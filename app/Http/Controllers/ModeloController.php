<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreModeloRequest;
use App\Models\Marca;
use App\Models\Modelo;
use Illuminate\http\Response;

class ModeloController extends Controller
{
    public function index(){
        //mostramos todos los registros de los diagnosticos
        $modelo = Modelo::all(); 
        return $modelo;
    }
    
    public function store(StoreModeloRequest $request)
    {

    //   creamos una nueva lista de los modelos; 
         $validated = $request->validated();
         //aqui buscamos la marca en  la base de datos
         $marca= Marca::where('marca',$validated['id_marca'])->first();//solo devuelve el resultado y si no lo encuentra devuelve null
         //aqui verificamos que marca tenga el valor
         if(!$marca){
            return response()->json([
                'message'=>'La marca no existe',
            ],
            Response:: HTTP_NOT_FOUND);//aqui significaria que no lo a encontrado el registro           
         }
        $modelo = $marca->modelos()->create([
        'nombre_modelo'=>$validated['nombre_modelo'],
        ]);
        return response()->json([
            'message'=>'modelo creado',
            'modelo'=>$modelo
        ],Response::HTTP_CREATED);
    }
    public function show(string $id ){
        $modelo = Modelo ::find($id);
        return $modelo;
    }
}
