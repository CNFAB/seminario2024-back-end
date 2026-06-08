<?php

namespace App\Http\Controllers;

use App\Http\Requests\precioReparacion\PrecioReparacionRequest;
use App\Http\Requests\precioReparacion\UpdatePrecioReparacionRequest;
use App\Models\PrecioReparacion;
use Illuminate\Http\JsonResponse;

class PrecioReparacionController extends Controller
{
     public function index(): JsonResponse
    {
        $precios = PrecioReparacion::with('categoria')->get();
        
        return response()->json([
            'success' => true,
            'count' => $precios->count(),
            'data' => $precios,
        ]);
    }
    
    /**
     * Store a newly created resource in storage.
     */
    public function store(PrecioReparacionRequest $request): JsonResponse
    {
        $precioReparacion = PrecioReparacion::create($request->validated());
        
        return response()->json([
            'success' => true,
            'message' => 'Precio de reparación creado exitosamente',
            'data' => $precioReparacion->load('categoria'),
        ], 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePrecioReparacionRequest $request, $id): JsonResponse
    {
        $precioReparacion = PrecioReparacion::with('categoria')->find($id);

        if (!$precioReparacion) {
            return response()->json([
                'success' => false,
                'message' => 'Precio de reparación no encontrado',
            ], 404);
        }

        // Verificar si hay datos para actualizar
        if (empty($request->validated())) {
            return response()->json([
                'success' => false,
                'message' => 'No se proporcionaron datos para actualizar',
            ], 400);
        }

        $precioReparacion->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Precio de reparación actualizado exitosamente',
            'data' => $precioReparacion->fresh()->load('categoria'),
        ]);
    }
}