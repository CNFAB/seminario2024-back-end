<?php

namespace App\Http\Controllers;

use App\Models\Pieza;
use App\Http\Requests\Pieza\StorePiezaRequest;
use App\Http\Requests\Pieza\UpdatePiezaRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PiezaController extends Controller
{
    /**
     * Obtiene todas las piezas con sus categorías (JSON)
     */
    public function index(): JsonResponse
    {
        try {
            $piezas = Pieza::with('categoria')
                ->orderBy('nombre_pieza')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $piezas,
                'message' => 'Piezas obtenidas exitosamente',
                'count' => $piezas->count()
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener piezas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene una pieza específica con su categoría (JSON)
     */
    public function show(int $id): JsonResponse
    {
        try {
            $pieza = Pieza::with('categoria')->find($id);
            
            if (!$pieza) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pieza no encontrada'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $pieza,
                'message' => 'Pieza obtenida exitosamente'
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener la pieza',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Almacena una nueva pieza (JSON)
     */
    public function store(StorePiezaRequest $request): JsonResponse
    {
        try {
            $pieza = Pieza::create($request->validated());
            
            // Cargar la relación de categoría para la respuesta
            $pieza->load('categoria');
            
            return response()->json([
                'success' => true,
                'data' => $pieza,
                'message' => 'Pieza creada exitosamente'
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la pieza',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualiza una pieza existente (JSON)
     */
    public function update(UpdatePiezaRequest $request, int $id): JsonResponse
    {
        try {
            $pieza = Pieza::find($id);
            
            if (!$pieza) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pieza no encontrada'
                ], 404);
            }
            
            $pieza->update($request->validated());
            
            // Recargar con categoría
            $pieza->load('categoria');
            
            return response()->json([
                'success' => true,
                'data' => $pieza,
                'message' => 'Pieza actualizada exitosamente'
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la pieza',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Elimina una pieza (JSON)
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $pieza = Pieza::find($id);
            
            if (!$pieza) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pieza no encontrada'
                ], 404);
            }
            
            $pieza->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Pieza eliminada exitosamente'
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la pieza',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene piezas por categoría (JSON)
     */
    public function porCategoria(int $categoriaId): JsonResponse
    {
        try {
            $piezas = Pieza::with('categoria')
                ->where('id_categoria', $categoriaId)
                ->orderBy('nombre_pieza')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $piezas,
                'message' => 'Piezas por categoría obtenidas exitosamente',
                'count' => $piezas->count()
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener piezas por categoría',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene piezas con stock disponible (JSON)
     */
    public function conStock(): JsonResponse
    {
        try {
            $piezas = Pieza::with('categoria')
                ->where('stock', '>', 0)
                ->orderBy('nombre_pieza')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $piezas,
                'message' => 'Piezas con stock obtenidas exitosamente',
                'count' => $piezas->count()
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener piezas con stock',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Buscar piezas por nombre (JSON)
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'query' => 'required|string|min:2'
            ]);
            
            $query = $request->input('query');
            $piezas = Pieza::with('categoria')
                ->where('nombre_pieza', 'LIKE', "%{$query}%")
                ->orderBy('nombre_pieza')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $piezas,
                'message' => 'Resultados de búsqueda',
                'count' => $piezas->count()
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error en la búsqueda',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar stock de una pieza (incrementar/disminuir)
     */
    public function actualizarStock(Request $request, int $id): JsonResponse
    {
        try {
            $request->validate([
                'cantidad' => 'required|integer',
                'operacion' => 'required|in:incrementar,disminuir'
            ]);
            
            $pieza = Pieza::find($id);
            
            if (!$pieza) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pieza no encontrada'
                ], 404);
            }
            
            $cantidad = $request->input('cantidad');
            $operacion = $request->input('operacion');
            
            if ($operacion === 'disminuir') {
                if ($pieza->stock < $cantidad) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Stock insuficiente',
                        'stock_actual' => $pieza->stock
                    ], 400);
                }
                $pieza->disminuirStock($cantidad);
            } else {
                $pieza->aumentarStock($cantidad);
            }
            
            $pieza->load('categoria');
            
            return response()->json([
                'success' => true,
                'data' => $pieza,
                'message' => 'Stock actualizado exitosamente'
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar stock',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}