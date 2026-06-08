<?php

namespace App\Http\Controllers;

use App\Http\Requests\Modelo\StoreModeloRequest;
use App\Http\Requests\Modelo\UpdateModeloRequest;
use App\Models\Marca;
use App\Models\Modelo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class ModeloController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            // Cargar la relación con marca para información completa
            $modelos = Modelo::with('marca')->get(); 
            
            return response()->json([
                'success' => true,
                'data' => $modelos
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los modelos',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    public function store(StoreModeloRequest $request): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            $validated = $request->validated();
            
            // Buscar por ID
            $marca = Marca::find($validated['id_marca']);
            
            if (!$marca) {
                return response()->json([
                    'success' => false,
                    'message' => 'La marca no existe',
                ], Response::HTTP_NOT_FOUND);
            }

            // Verificar si ya existe el modelo en esta marca
            $modeloExistente = Modelo::where('id_marca', $validated['id_marca'])
                ->where('nombre_modelo', $validated['nombre_modelo'])
                ->first();

            if ($modeloExistente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este modelo ya existe para la marca seleccionada'
                ], Response::HTTP_CONFLICT);
            }

            // Crear el modelo con todos los campos
            $modelo = $marca->modelos()->create([
                'nombre_modelo' => $validated['nombre_modelo'],
                'ram' => $validated['ram'] ?? null,
                'almacenamiento' => $validated['almacenamiento'] ?? null,
                'procesador' => $validated['procesador'] ?? null,
                'pantalla' => $validated['pantalla'] ?? null,
                'bateria' => $validated['bateria'] ?? null,
            ]);

            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Modelo creado exitosamente',
                'data' => $modelo->load('marca')
            ], Response::HTTP_CREATED);
            
        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack();
            
            if (str_contains($e->getMessage(), 'uk_modelo_marca_nombre')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este modelo ya existe para la marca seleccionada'
                ], Response::HTTP_CONFLICT);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el modelo',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Error inesperado',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $modelo = Modelo::with('marca', 'dispositivos')->find($id);
            
            if (!$modelo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Modelo no encontrado'
                ], Response::HTTP_NOT_FOUND);
            }
            
            return response()->json([
                'success' => true,
                'data' => $modelo
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el modelo',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    public function porMarca($id_marca)
    {
        // Verificar que la marca existe
        $marca = Marca::find($id_marca);
        
        if (!$marca) {
            return response()->json([
                'success' => false,
                'message' => 'Marca no encontrada'
            ], 404);
        }

        // Obtener modelos de esa marca con especificaciones
        $modelos = Modelo::where('id_marca', $id_marca)
            ->select('id_modelo', 'nombre_modelo', 'ram', 'almacenamiento', 'procesador', 'pantalla', 'bateria')
            ->get();

        return response()->json([
            'success' => true,
            'marca' => $marca->marca,
            'total' => $modelos->count(),
            'data' => $modelos
        ]);
    }

    public function update(UpdateModeloRequest $request, string $id): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            $modelo = Modelo::with('marca')->find($id);
            
            if (!$modelo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Modelo no encontrado'
                ], Response::HTTP_NOT_FOUND);
            }

            $validated = $request->validated();
            
            // Si se está actualizando la marca, verificar que exista
            if (isset($validated['id_marca'])) {
                $marca = Marca::find($validated['id_marca']);
                if (!$marca) {
                    return response()->json([
                        'success' => false,
                        'message' => 'La marca no existe'
                    ], Response::HTTP_NOT_FOUND);
                }
            }

            // Preparar los datos para actualizar
            $updateData = [];
            
            if (isset($validated['id_marca'])) {
                $updateData['id_marca'] = $validated['id_marca'];
            }
            if (isset($validated['nombre_modelo'])) {
                $updateData['nombre_modelo'] = $validated['nombre_modelo'];
            }
            if (array_key_exists('ram', $validated)) {
                $updateData['ram'] = $validated['ram'];
            }
            if (array_key_exists('almacenamiento', $validated)) {
                $updateData['almacenamiento'] = $validated['almacenamiento'];
            }
            if (array_key_exists('procesador', $validated)) {
                $updateData['procesador'] = $validated['procesador'];
            }
            if (array_key_exists('pantalla', $validated)) {
                $updateData['pantalla'] = $validated['pantalla'];
            }
            if (array_key_exists('bateria', $validated)) {
                $updateData['bateria'] = $validated['bateria'];
            }

            // Actualizar el modelo
            $modelo->update($updateData);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Modelo actualizado exitosamente',
                'data' => $modelo->fresh('marca')
            ]);
            
        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack();
            
            if (str_contains($e->getMessage(), 'uk_modelo_marca_nombre')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este modelo ya existe para la marca seleccionada'
                ], Response::HTTP_CONFLICT);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el modelo',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Error inesperado',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            $modelo = Modelo::withCount('dispositivos')->find($id);
            
            if (!$modelo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Modelo no encontrado'
                ], Response::HTTP_NOT_FOUND);
            }

            // Verificar si tiene dispositivos asociados
            if ($modelo->dispositivos_count > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar el modelo porque tiene dispositivos asociados'
                ], Response::HTTP_CONFLICT);
            }

            $modelo->delete();
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Modelo eliminado exitosamente'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el modelo',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // Método adicional: Obtener modelos por marca con especificaciones completas
    public function getByMarca(string $marcaId): JsonResponse
    {
        try {
            $marca = Marca::find($marcaId);
            if (!$marca) {
                return response()->json([
                    'success' => false,
                    'message' => 'Marca no encontrada'
                ], Response::HTTP_NOT_FOUND);
            }

            $modelos = Modelo::where('id_marca', $marcaId)
                ->with('marca')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $modelos
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los modelos de la marca',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}