<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePostRequest;
use App\Models\Cliente;
use Illuminate\Http\Request;


class ClienteController extends Controller
{
    /**
     * Display a listing of the resource.
     */

    public function index()
    {
        //
       $cliente = Cliente::all(); 
        return $cliente;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePostRequest $request)
    {

    //    $validated =  $request->validated();         
         $validated = $request->validated();
        
        $cliente = new Cliente;
        $cliente->fill($validated);
        // $cliente->nombre = $validated['nombre'];
        // $cliente->apellido = $validated['apellido'];
        // $cliente->numero_celular = $request-> input('numero_celular');
        // $cliente->correo = $request-> input('correo');

        $cliente->save();

        return $cliente;
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    { 
        // muestra un solo dato no sin la condicion
        $cliente = Cliente ::find($id);
    return  $cliente ;
    
    }

    public function condicion(string $id){
        $cliente = Cliente :: where('id_cliente','>',$id)->get() ;
        return  $cliente;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $cliente = Cliente ::find($id);
        $cliente->update(['numero_celular' => $request->input('numero_celular')]);
        $cliente->save();

        return response()->json($cliente);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $idCliente)
    {
        $cliente = Cliente ::find($idCliente);
        if(!$cliente){
            return response()->json(['error'=>'cliente No encontrado'],404);
        }
        $cliente->delete();
        return response()->json(['Message'=>'cliente elimnado']);
    }

    public function filtro (Request $request){
        $cliente = Cliente:: select(['nombre','apellido']); 
            //aqui solo se muestra las consultas de esos dos
        if($request->apellido !=null){
            $cliente = $cliente->where('apellido',$request->apellido);
            //$cliente = $cliente->where('apellido',$request->algo[0],$request->algo[1]);
        }
        if($request->nombre !=null){
        $cliente = $cliente->where('nombre',$request->nombre);

            //$cliente = $cliente->where('apellido',$request->algo[0],$request->algo[1]);
        }
        if($request->id_cliente !=null){
            $cliente = $cliente->where('id_cliente',$request->id_cliente[0],$request->id_cliente[1]);
        }
        $cliente = $cliente->orderBy('apellido');// ordenar la foma en la base 

        //aqui vamos a ver la paginacion dinamica.
      //  $tamaniopag = $request->tamaniopag !=null ? $request->tamaniopag : 10;  
        
       // return $cliente->paginate($tamaniopag); paginacion
         //return $cliente->get();
         return ["cantidad"=>$cliente->count()];//podrias hacer el tema de la sum,avg,count(),
         //para haer la paginacion podris porer return cliente->paginate(aqui va el numero de la pag);s
    }
}
