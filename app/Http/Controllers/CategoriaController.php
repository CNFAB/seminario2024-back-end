<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Http\Requests\Categoria\StoreCategoriaRequest;
use App\Http\Requests\Categoria\UpdateCategoriaRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CategoriaController extends Controller
{
    /**
     * Obtiene todas las categorías (JSON)
     */
    public function index(): JsonResponse
    {
        try {
            $categorias = Categoria::orderBy('categoria')->get();
            
            return response()->json([
                'success' => true,
                'data' => $categorias,
                'message' => 'Categorías obtenidas exitosamente',
                'count' => $categorias->count()
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener categorías',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene una categoría por su NOMBRE (JSON)
     */
    public function show(string $categoriaNombre): JsonResponse
    {
        try {
            $categoria = Categoria::where('categoria', $categoriaNombre)->first();
            
            if (!$categoria) {
                return response()->json([
                    'success' => false,
                    'message' => 'Categoría no encontrada'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $categoria,
                'message' => 'Categoría obtenida exitosamente'
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener la categoría',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Almacena una nueva categoría (JSON)
     */
    public function store(StoreCategoriaRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            
            // Si no viene garantia_dias, usar 90 por defecto
            if (!isset($data['garantia_dias'])) {
                $data['garantia_dias'] = 90;
            }
            
            $categoria = Categoria::create($data);
            
            return response()->json([
                'success' => true,
                'data' => $categoria,
                'message' => 'Categoría creada exitosamente'
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la categoría',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualiza una categoría existente por su ID (JSON)
     */
    public function update(UpdateCategoriaRequest $request, int $id): JsonResponse
    {
        try {
            $categoria = Categoria::find($id);
            
            if (!$categoria) {
                return response()->json([
                    'success' => false,
                    'message' => 'Categoría no encontrada'
                ], 404);
            }
            
            $data = $request->validated();
            
            // Actualizar solo los campos que vienen
            $categoria->update($data);
            
            return response()->json([
                'success' => true,
                'data' => $categoria,
                'message' => 'Categoría actualizada exitosamente'
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la categoría',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Elimina una categoría por su NOMBRE (JSON)
     */
    public function destroy(string $categoriaNombre): JsonResponse
    {
        try {
            $categoria = Categoria::where('categoria', $categoriaNombre)->first();
            
            if (!$categoria) {
                return response()->json([
                    'success' => false,
                    'message' => 'Categoría no encontrada'
                ], 404);
            }
            
            // Verificar si tiene piezas asociadas
            if ($categoria->piezas()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar la categoría porque tiene piezas asociadas'
                ], 422);
            }
            
            $categoria->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Categoría eliminada exitosamente'
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la categoría',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Buscar categorías (puede ser por nombre parcial)
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'query' => 'required|string|min:2'
            ]);
            
            $query = $request->input('query');
            $categorias = Categoria::where('categoria', 'LIKE', "%{$query}%")
                ->orderBy('categoria')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $categorias,
                'message' => 'Resultados de búsqueda',
                'count' => $categorias->count()
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
     * Verificar si una categoría existe
     */
    public function exists(string $categoriaNombre): JsonResponse
    {
        try {
            $exists = Categoria::where('categoria', $categoriaNombre)->exists();
            
            return response()->json([
                'success' => true,
                'exists' => $exists,
                'message' => $exists ? 'La categoría existe' : 'La categoría no existe'
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al verificar la categoría',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ==========================================
    // NUEVOS MÉTODOS PARA GARANTÍA
    // ==========================================

    /**
     * Obtener categorías con garantía activa (días > 0)
     */
    public function conGarantia(): JsonResponse
    {
        try {
            $categorias = Categoria::conGarantia()->orderBy('categoria')->get();
            
            return response()->json([
                'success' => true,
                'data' => $categorias,
                'message' => 'Categorías con garantía obtenidas exitosamente',
                'count' => $categorias->count()
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener categorías con garantía',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar solo los días de garantía de una categoría
     */
    public function actualizarGarantia(Request $request, int $id): JsonResponse
    {
        try {
            $categoria = Categoria::find($id);
            
            if (!$categoria) {
                return response()->json([
                    'success' => false,
                    'message' => 'Categoría no encontrada'
                ], 404);
            }
            
            $request->validate([
                'garantia_dias' => 'required|integer|min:0|max:1095'
            ]);
            
            $categoria->garantia_dias = $request->garantia_dias;
            $categoria->save();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $categoria->id_categoria,
                    'categoria' => $categoria->categoria,
                    'garantia_dias' => $categoria->garantia_dias,
                    'garantia_texto' => $categoria->garantia_texto,
                    'meses_garantia' => $categoria->meses_garantia
                ],
                'message' => 'Días de garantía actualizados exitosamente'
            ], 200);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar los días de garantía',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener garantía de una categoría específica
     */
    public function getGarantia(int $id): JsonResponse
    {
        try {
            $categoria = Categoria::find($id);
            
            if (!$categoria) {
                return response()->json([
                    'success' => false,
                    'message' => 'Categoría no encontrada'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $categoria->id_categoria,
                    'categoria' => $categoria->categoria,
                    'garantia_dias' => $categoria->garantia_dias,
                    'garantia_texto' => $categoria->garantia_texto,
                    'meses_garantia' => $categoria->meses_garantia
                ],
                'message' => 'Garantía de la categoría obtenida exitosamente'
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener la garantía',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}