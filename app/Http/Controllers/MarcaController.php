<?php

namespace App\Http\Controllers;

use App\Http\Requests\Marca\StoreMarcaRequest;
use App\Http\Requests\Marca\UpdateMarcaRequest;
use App\Models\Marca;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class MarcaController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $marcas = Marca::withCount('modelos')->get();
            
            return response()->json([
                'success' => true,
                'data' => $marcas
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las marcas',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    public function store(StoreMarcaRequest $request): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            $validated = $request->validated();
            
            $marca = Marca::create([
                'marca' => $validated['marca']
            ]);

            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Marca creada exitosamente',
                'data' => $marca
            ], Response::HTTP_CREATED);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la marca',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $marca = Marca::with('modelos')->find($id);
            
            if (!$marca) {
                return response()->json([
                    'success' => false,
                    'message' => 'Marca no encontrada'
                ], Response::HTTP_NOT_FOUND);
            }
            
            return response()->json([
                'success' => true,
                'data' => $marca
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener la marca',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(UpdateMarcaRequest $request, string $id): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            $marca = Marca::find($id);
            
            if (!$marca) {
                return response()->json([
                    'success' => false,
                    'message' => 'Marca no encontrada'
                ], Response::HTTP_NOT_FOUND);
            }

            $validated = $request->validated();
            $marca->update($validated);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Marca actualizada exitosamente',
                'data' => $marca
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la marca',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            $marca = Marca::withCount('modelos')->find($id);
            
            if (!$marca) {
                return response()->json([
                    'success' => false,
                    'message' => 'Marca no encontrada'
                ], Response::HTTP_NOT_FOUND);
            }

            if ($marca->modelos_count > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar la marca porque tiene modelos asociados'
                ], Response::HTTP_CONFLICT);
            }

            $marca->delete();
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Marca eliminada exitosamente'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la marca',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}