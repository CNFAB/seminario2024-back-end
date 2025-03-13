<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDispositivoRequest;
use App\Models\Cliente;
use App\Models\Dispositivo;
use App\Models\Modelo;
use Illuminate\http\Response;

class DispositivoController extends Controller
{
    public function index()
    {
        //mostramos todos los registros de los diagnosticos
        $dispositivo = Dispositivo::all();
        return $dispositivo;
    }
    public function store(StoreDispositivoRequest $request)
    {

        $validated = $request->validated();
        $cliente = Cliente::where('correo', $validated['id_cliente'])->orWhere('numero_celular', $validated['id_cliente'])
            ->first(); //aqui busca por el correo o por el numero de celular 
        $modelo = Modelo::where('nombre_modelo', $validated['id_modelo'])->first();
        if (!$cliente) {
            return response()->json(
                [
                    'message' => 'el correo o el numero de celular no existe',
                ],
                Response::HTTP_NOT_FOUND
            );
        }
        if (!$modelo) {
            return response()->json(
                [
                    'message' => 'el modelo no existe'
                ],
                Response::HTTP_NOT_FOUND
            );
        }
        $dispositivo = $cliente->dispositivos()->create([
            'id_modelo' => $modelo->id_modelo,
            'imei' => $validated['imei'],
        ]);
        return response()->json([
            'message' => 'dispositivo creado',
            'dispositivo' => $dispositivo
        ], RESPONSE::HTTP_CREATED);
    }
    public function show(string $id)
    {
        $dispositivo = Dispositivo::find($id);
        return $dispositivo;
    }
}
