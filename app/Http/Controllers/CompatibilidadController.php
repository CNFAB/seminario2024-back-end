<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Pieza;
use App\Models\Modelo;
use App\Models\Compatibilidad;
use App\Http\Requests\Compatibilidad\StoreCompatibilidadRequest;
use App\Http\Requests\Compatibilidad\UpdateCompatibilidadRequest;
use App\Http\Requests\SyncCompatibilidadesRequest;
use App\Http\Requests\DeleteCompatibilidadRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CompatibilidadController extends Controller
{
    /**
     * Constructor - aplicar middleware si es necesario
     */
    public function __construct()
    {
        // Ejemplo de middleware de autenticación
        // $this->middleware('auth:api')->except(['index', 'show']);
        // $this->middleware('role:admin')->only(['destroy', 'sync']);
    }

    /**
     * LISTAR TODAS LAS COMPATIBILIDADES
     * GET /api/compatibilidades
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Compatibilidad::with(['pieza.categoria', 'modelo.marca']);

            // Filtros opcionales
            if ($request->has('id_pieza')) {
                $query->where('id_pieza', $request->id_pieza);
            }

            if ($request->has('id_modelo')) {
                $query->where('id_modelo', $request->id_modelo);
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->whereHas('pieza', function ($subQ) use ($search) {
                        $subQ->where('nombre_pieza', 'ILIKE', "%{$search}%");
                    })->orWhereHas('modelo', function ($subQ) use ($search) {
                        $subQ->where('nombre_modelo', 'ILIKE', "%{$search}%");
                    })->orWhere('descripcion', 'ILIKE', "%{$search}%");
                });
            }

            // Paginación
            $perPage = $request->get('per_page', 15);
            $compatibilidades = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Compatibilidades obtenidas exitosamente',
                'data' => $compatibilidades
            ], 200);

        } catch (\Exception $e) {
            return $this->handleError($e, 'Error al obtener las compatibilidades');
        }
    }
    public function getAll(Request $request): JsonResponse
{
    try {
        $query = Compatibilidad::with(['pieza.categoria', 'modelo.marca']);

        // Filtros opcionales
        if ($request->has('id_pieza')) {
            $query->where('id_pieza', $request->id_pieza);
        }

        if ($request->has('id_modelo')) {
            $query->where('id_modelo', $request->id_modelo);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('pieza', function ($subQ) use ($search) {
                    $subQ->where('nombre_pieza', 'ILIKE', "%{$search}%");
                })->orWhereHas('modelo', function ($subQ) use ($search) {
                    $subQ->where('nombre_modelo', 'ILIKE', "%{$search}%");
                })->orWhere('descripcion', 'ILIKE', "%{$search}%");
            });
        }

        $compatibilidades = $query->get();

        return response()->json([
            'success' => true,
            'message' => 'Todas las compatibilidades obtenidas exitosamente',
            'data' => $compatibilidades
        ], 200);

    } catch (\Exception $e) {
        return $this->handleError($e, 'Error al obtener las compatibilidades');
    }
}

    /**
     * OBTENER COMPATIBILIDAD POR ID
     * GET /api/compatibilidades/{id}
     */
    public function show($id): JsonResponse
    {
        try {
            $compatibilidad = Compatibilidad::with([
                'pieza.categoria', 
                'modelo.marca',
                'pieza.compatibilidades.modelo'
            ])->findOrFail($id);

            // Obtener compatibilidades relacionadas (otras piezas para el mismo modelo)
            $relacionadas = Compatibilidad::with('pieza')
                ->where('id_modelo', $compatibilidad->id_modelo)
                ->where('id_compatible', '!=', $id)
                ->limit(5)
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Compatibilidad encontrada',
                'data' => [
                    'compatibilidad' => $compatibilidad,
                    'compatibilidades_relacionadas' => $relacionadas
                ]
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Compatibilidad no encontrada'
            ], 404);
        } catch (\Exception $e) {
            return $this->handleError($e, 'Error al obtener la compatibilidad');
        }
    }

    /**
     * CREAR NUEVA COMPATIBILIDAD
     * POST /api/compatibilidades
     */
    public function store(StoreCompatibilidadRequest $request): JsonResponse
    {
        try {
            // Verificar combinación válida
            if (!$request->isValidCombination()) {
                return response()->json([
                    'success' => false,
                    'message' => 'La combinación de pieza y modelo no es válida según las reglas de negocio'
                ], 422);
            }

            // Crear la compatibilidad
            $compatibilidad = Compatibilidad::create($request->getCompatibilityData());
            
            // Cargar relaciones
            $compatibilidad->load(['pieza.categoria', 'modelo.marca']);
            
            // Obtener datos para auditoría
            $auditData = $request->getAuditData();

            // Registrar en log
            \Log::info('Nueva compatibilidad creada', [
                'user_id' => auth()->id(),
                'compatibilidad_id' => $compatibilidad->id_compatible,
                'data' => $auditData
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Compatibilidad creada exitosamente',
                'data' => [
                    'compatibilidad' => $compatibilidad,
                    'audit' => $auditData
                ]
            ], 201);

        } catch (\Exception $e) {
            return $this->handleError($e, 'Error al crear la compatibilidad');
        }
    }

    /**
     * ACTUALIZAR COMPATIBILIDAD
     * PUT /api/compatibilidades/{id}
     * PATCH /api/compatibilidades/{id}
     */
    public function update(UpdateCompatibilidadRequest $request, $id): JsonResponse
    {
        try {
            // Buscar la compatibilidad
            $compatibilidad = Compatibilidad::findOrFail($id);
            
            // Obtener solo los datos modificados
            $modifiedData = $request->getModifiedData();
            
            // Verificar si hay cambios
            if (!$request->hasChanges($compatibilidad)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se detectaron cambios para actualizar',
                    'data' => $compatibilidad->load(['pieza', 'modelo'])
                ], 200);
            }
            
            // Guardar datos anteriores para auditoría
            $oldData = [
                'id_pieza' => $compatibilidad->id_pieza,
                'id_modelo' => $compatibilidad->id_modelo,
                'descripcion' => $compatibilidad->descripcion
            ];
            
            // Actualizar la compatibilidad
            $compatibilidad->update($modifiedData);
            
            // Cargar relaciones para la respuesta
            $compatibilidad->load(['pieza.categoria', 'modelo.marca']);
            
            // Preparar mensaje de respuesta
            $updatingFields = $request->getUpdatingFields();
            $fieldsMessage = implode(', ', $updatingFields);
            
            // Registrar en log
            \Log::info('Compatibilidad actualizada', [
                'user_id' => auth()->id(),
                'compatibilidad_id' => $id,
                'old_data' => $oldData,
                'new_data' => $modifiedData
            ]);
            
            return response()->json([
                'success' => true,
                'message' => "Compatibilidad actualizada exitosamente. Campos actualizados: {$fieldsMessage}",
                'data' => [
                    'compatibilidad' => $compatibilidad,
                    'old_data' => $oldData,
                    'new_data' => $modifiedData,
                    'updated_fields' => $updatingFields
                ]
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'La compatibilidad no existe'
            ], 404);
        } catch (\Exception $e) {
            return $this->handleError($e, 'Error al actualizar la compatibilidad');
        }
    }

    /**
     * ELIMINAR COMPATIBILIDAD
     * DELETE /api/compatibilidades/{id}
     */
    public function destroy($id): JsonResponse
    {
        try {
            $compatibilidad = Compatibilidad::findOrFail($id);
            
            // Guardar datos para auditoría antes de eliminar
            $dataEliminada = [
                'id_compatible' => $compatibilidad->id_compatible,
                'id_pieza' => $compatibilidad->id_pieza,
                'id_modelo' => $compatibilidad->id_modelo,
                'descripcion' => $compatibilidad->descripcion,
                'pieza_nombre' => $compatibilidad->pieza->nombre_pieza ?? null,
                'modelo_nombre' => $compatibilidad->modelo->nombre_modelo ?? null
            ];
            
            $compatibilidad->delete();
            
            // Registrar en log
            \Log::info('Compatibilidad eliminada', [
                'user_id' => auth()->id(),
                'data' => $dataEliminada
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Compatibilidad eliminada exitosamente',
                'data' => $dataEliminada
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'La compatibilidad no existe'
            ], 404);
        } catch (\Exception $e) {
            return $this->handleError($e, 'Error al eliminar la compatibilidad');
        }
    }

    /**
     * ELIMINAR MÚLTIPLES COMPATIBILIDADES
     * DELETE /api/compatibilidades
     */
    public function destroyMultiple(DeleteCompatibilidadRequest $request): JsonResponse
    {
        try {
            $ids = $request->ids;
            
            // Obtener datos para auditoría antes de eliminar
            $compatibilidades = Compatibilidad::with(['pieza', 'modelo'])
                ->whereIn('id_compatible', $ids)
                ->get();
            
            $datosEliminados = $compatibilidades->map(function ($item) {
                return [
                    'id_compatible' => $item->id_compatible,
                    'pieza' => $item->pieza->nombre_pieza ?? null,
                    'modelo' => $item->modelo->nombre_modelo ?? null,
                    'descripcion' => $item->descripcion
                ];
            });
            
            // Eliminar
            Compatibilidad::whereIn('id_compatible', $ids)->delete();
            
            // Registrar en log
            \Log::info('Múltiples compatibilidades eliminadas', [
                'user_id' => auth()->id(),
                'ids' => $ids,
                'cantidad' => count($ids),
                'datos' => $datosEliminados
            ]);
            
            return response()->json([
                'success' => true,
                'message' => count($ids) . ' compatibilidades eliminadas exitosamente',
                'data' => [
                    'ids_eliminados' => $ids,
                    'compatibilidades' => $datosEliminados
                ]
            ], 200);
            
        } catch (\Exception $e) {
            return $this->handleError($e, 'Error al eliminar las compatibilidades');
        }
    }

    /**
     * SINCRONIZAR COMPATIBILIDADES PARA UNA PIEZA
     * POST /api/piezas/{piezaId}/compatibilidades/sync
     */
    public function syncForPieza(SyncCompatibilidadesRequest $request, $piezaId): JsonResponse
    {
        try {
            $pieza = Pieza::findOrFail($piezaId);
            $syncData = $request->getSyncData();
            
            // Guardar estado anterior para auditoría
            $oldCompatibilidades = $pieza->modelos()->get()->map(function ($modelo) {
                return [
                    'id_modelo' => $modelo->id_modelo,
                    'nombre' => $modelo->nombre_modelo,
                    'descripcion' => $modelo->pivot->descripcion ?? null
                ];
            });
            
            // Sincronizar
            $pieza->modelos()->sync($syncData);
            
            // Obtener nuevo estado
            $newCompatibilidades = $pieza->modelos()->withPivot('descripcion')->get();
            
            // Registrar en log
            \Log::info('Compatibilidades sincronizadas para pieza', [
                'user_id' => auth()->id(),
                'pieza_id' => $piezaId,
                'pieza_nombre' => $pieza->nombre_pieza,
                'old' => $oldCompatibilidades,
                'new' => $newCompatibilidades
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Compatibilidades sincronizadas exitosamente',
                'data' => [
                    'pieza' => $pieza->load('categoria'),
                    'compatibilidades_anteriores' => $oldCompatibilidades,
                    'compatibilidades_nuevas' => $newCompatibilidades
                ]
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Pieza no encontrada'
            ], 404);
        } catch (\Exception $e) {
            return $this->handleError($e, 'Error al sincronizar compatibilidades');
        }
    }

    /**
     * SINCRONIZAR COMPATIBILIDADES PARA UN MODELO
     * POST /api/modelos/{modeloId}/compatibilidades/sync
     */
    public function syncForModelo(SyncCompatibilidadesRequest $request, $modeloId): JsonResponse
    {
        try {
            $modelo = Modelo::findOrFail($modeloId);
            $syncData = $request->getSyncData();
            
            // Guardar estado anterior para auditoría
            $oldCompatibilidades = $modelo->piezas()->get()->map(function ($pieza) {
                return [
                    'id_pieza' => $pieza->id_pieza,
                    'nombre' => $pieza->nombre_pieza,
                    'descripcion' => $pieza->pivot->descripcion ?? null
                ];
            });
            
            // Sincronizar
            $modelo->piezas()->sync($syncData);
            
            // Obtener nuevo estado
            $newCompatibilidades = $modelo->piezas()->withPivot('descripcion')->get();
            
            // Registrar en log
            \Log::info('Compatibilidades sincronizadas para modelo', [
                'user_id' => auth()->id(),
                'modelo_id' => $modeloId,
                'modelo_nombre' => $modelo->nombre_modelo,
                'old' => $oldCompatibilidades,
                'new' => $newCompatibilidades
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Compatibilidades sincronizadas exitosamente',
                'data' => [
                    'modelo' => $modelo->load('marca'),
                    'compatibilidades_anteriores' => $oldCompatibilidades,
                    'compatibilidades_nuevas' => $newCompatibilidades
                ]
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Modelo no encontrado'
            ], 404);
        } catch (\Exception $e) {
            return $this->handleError($e, 'Error al sincronizar compatibilidades');
        }
    }

    /**
     * OBTENER COMPATIBILIDADES POR PIEZA
     * GET /api/piezas/{piezaId}/compatibilidades
     */
    public function getByPieza($piezaId): JsonResponse
    {
        try {
            $pieza = Pieza::findOrFail($piezaId);
            
            $compatibilidades = Compatibilidad::with(['modelo.marca'])
                ->where('id_pieza', $piezaId)
                ->get();
            
            return response()->json([
                'success' => true,
                'message' => 'Compatibilidades de la pieza obtenidas exitosamente',
                'data' => [
                    'pieza' => $pieza->load('categoria'),
                    'total_compatibilidades' => $compatibilidades->count(),
                    'compatibilidades' => $compatibilidades
                ]
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Pieza no encontrada'
            ], 404);
        } catch (\Exception $e) {
            return $this->handleError($e, 'Error al obtener compatibilidades de la pieza');
        }
    }

    /**
     * OBTENER COMPATIBILIDADES POR MODELO
     * GET /api/modelos/{modeloId}/compatibilidades
     */
    public function getByModelo($modeloId): JsonResponse
    {
        try {
            $modelo = Modelo::findOrFail($modeloId);
            
            $compatibilidades = Compatibilidad::with(['pieza.categoria'])
                ->where('id_modelo', $modeloId)
                ->get();
            
            return response()->json([
                'success' => true,
                'message' => 'Compatibilidades del modelo obtenidas exitosamente',
                'data' => [
                    'modelo' => $modelo->load('marca'),
                    'total_compatibilidades' => $compatibilidades->count(),
                    'compatibilidades' => $compatibilidades
                ]
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Modelo no encontrado'
            ], 404);
        } catch (\Exception $e) {
            return $this->handleError($e, 'Error al obtener compatibilidades del modelo');
        }
    }

    /**
     * VERIFICAR COMPATIBILIDAD
     * GET /api/verificar-compatibilidad
     */
    public function verificar(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'id_pieza' => 'required|integer|exists:pieza,id_pieza',
                'id_modelo' => 'required|integer|exists:modelo,id_modelo'
            ]);
            
            $existe = Compatibilidad::where('id_pieza', $request->id_pieza)
                ->where('id_modelo', $request->id_modelo)
                ->exists();
            
            $compatibilidad = null;
            if ($existe) {
                $compatibilidad = Compatibilidad::with(['pieza', 'modelo'])
                    ->where('id_pieza', $request->id_pieza)
                    ->where('id_modelo', $request->id_modelo)
                    ->first();
            }
            
            return response()->json([
                'success' => true,
                'message' => $existe ? 'Las piezas son compatibles' : 'Las piezas NO son compatibles',
                'data' => [
                    'compatible' => $existe,
                    'id_pieza' => $request->id_pieza,
                    'id_modelo' => $request->id_modelo,
                    'compatibilidad' => $compatibilidad
                ]
            ], 200);
            
        } catch (\Exception $e) {
            return $this->handleError($e, 'Error al verificar compatibilidad');
        }
    }

    /**
     * OBTENER MODELOS COMPATIBLES CON UNA PIEZA (usando relación muchos a muchos)
     * GET /api/piezas/{piezaId}/modelos-compatibles
     */
    public function getModelosCompatibles($piezaId): JsonResponse
    {
        try {
            $pieza = Pieza::with(['modelos' => function ($query) {
                $query->with('marca')->withPivot('descripcion');
            }])->findOrFail($piezaId);
            
            return response()->json([
                'success' => true,
                'message' => 'Modelos compatibles obtenidos exitosamente',
                'data' => [
                    'pieza' => [
                        'id' => $pieza->id_pieza,
                        'nombre' => $pieza->nombre_pieza,
                        'stock' => $pieza->stock
                    ],
                    'total_modelos' => $pieza->modelos->count(),
                    'modelos' => $pieza->modelos
                ]
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Pieza no encontrada'
            ], 404);
        } catch (\Exception $e) {
            return $this->handleError($e, 'Error al obtener modelos compatibles');
        }
    }

    /**
     * OBTENER PIEZAS COMPATIBLES CON UN MODELO (usando relación muchos a muchos)
     * GET /api/modelos/{modeloId}/piezas-compatibles
     */
    public function getPiezasCompatibles($modeloId): JsonResponse
    {
        try {
            $modelo = Modelo::with(['piezas' => function ($query) {
                $query->with('categoria')->withPivot('descripcion');
            }])->findOrFail($modeloId);
            
            return response()->json([
                'success' => true,
                'message' => 'Piezas compatibles obtenidas exitosamente',
                'data' => [
                    'modelo' => [
                        'id' => $modelo->id_modelo,
                        'nombre' => $modelo->nombre_modelo,
                        'marca' => $modelo->marca->marca ?? null
                    ],
                    'total_piezas' => $modelo->piezas->count(),
                    'piezas' => $modelo->piezas
                ]
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Modelo no encontrado'
            ], 404);
        } catch (\Exception $e) {
            return $this->handleError($e, 'Error al obtener piezas compatibles');
        }
    }

    /**
     * ESTADÍSTICAS DE COMPATIBILIDAD
     * GET /api/compatibilidades/estadisticas
     */
    public function estadisticas(): JsonResponse
    {
        try {
            $totalCompatibilidades = Compatibilidad::count();
            $totalPiezasConCompatibilidades = Compatibilidad::distinct('id_pieza')->count('id_pieza');
            $totalModelosConCompatibilidades = Compatibilidad::distinct('id_modelo')->count('id_modelo');
            
            $topPiezas = Compatibilidad::select('id_pieza')
                ->with('pieza')
                ->selectRaw('count(*) as total')
                ->groupBy('id_pieza')
                ->orderByDesc('total')
                ->limit(5)
                ->get()
                ->map(function ($item) {
                    return [
                        'pieza' => $item->pieza->nombre_pieza ?? 'Desconocida',
                        'total_compatibilidades' => $item->total
                    ];
                });
            
            $topModelos = Compatibilidad::select('id_modelo')
                ->with('modelo')
                ->selectRaw('count(*) as total')
                ->groupBy('id_modelo')
                ->orderByDesc('total')
                ->limit(5)
                ->get()
                ->map(function ($item) {
                    return [
                        'modelo' => $item->modelo->nombre_modelo ?? 'Desconocido',
                        'marca' => $item->modelo->marca->marca ?? null,
                        'total_compatibilidades' => $item->total
                    ];
                });
            
            return response()->json([
                'success' => true,
                'message' => 'Estadísticas obtenidas exitosamente',
                'data' => [
                    'totales' => [
                        'compatibilidades' => $totalCompatibilidades,
                        'piezas_con_compatibilidades' => $totalPiezasConCompatibilidades,
                        'modelos_con_compatibilidades' => $totalModelosConCompatibilidades
                    ],
                    'top_piezas' => $topPiezas,
                    'top_modelos' => $topModelos
                ]
            ], 200);
            
        } catch (\Exception $e) {
            return $this->handleError($e, 'Error al obtener estadísticas');
        }
    }

    /**
     * Manejar errores de forma consistente
     */
    private function handleError(\Exception $e, string $message): JsonResponse
    {
        \Log::error($message, [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'success' => false,
            'message' => $message,
            'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
        ], 500);
    }
}