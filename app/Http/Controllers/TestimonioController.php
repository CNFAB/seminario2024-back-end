<?php

namespace App\Http\Controllers;

use App\Models\Testimonio;
use App\Models\Reparacion;
use App\Models\Ingreso_d; 
use App\Http\Requests\Testimonio\StoreTestimonioRequest;
use App\Http\Requests\Testimonio\UpdateTestimonioEstadoRequest;
use App\Http\Requests\Testimonio\UpdateSkipTestimonioRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TestimonioController extends Controller
{
    /**
     * ============================================
     * PARA EL CLIENTE (FRONTEND PÚBLICO)
     * ============================================
     */
    
    /**
     * Obtener testimonios APROBADOS para mostrar en la web
     * GET /api/testimonios
     */
    public function index(): JsonResponse
    {
        try {
            $testimonios = Testimonio::aprobados()
                ->with('reparacion')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function($testimonio) {
                    return [
                        'id' => $testimonio->id_testimonio,
                        'cliente_nombre' => $testimonio->nombre_cliente,
                        'calificacion' => $testimonio->calificacion_estrella,
                        'comentario' => $testimonio->comentario,
                        'dispositivo' => $testimonio->dispositivo,
                        'fecha' => $testimonio->fecha_formateada
                    ];
                });
            
            return response()->json([
                'success' => true,
                'data' => $testimonios
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error al obtener testimonios: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los testimonios'
            ], 500);
        }
    }
    
    
    /**
     * Obtener estadísticas de calificaciones
     * GET /api/testimonios/estadisticas
     */
    public function estadisticas(): JsonResponse
    {
        try {
            $testimonios = Testimonio::where('estado', 'APROBADO')
                ->where('calificacion_estrella', '>=', 1)
                ->get();
            
            $total = $testimonios->count();
            $distribucion = [
                5 => 0,
                4 => 0,
                3 => 0,
                2 => 0,
                1 => 0
            ];
            
            $sumaCalificaciones = 0;
            
            foreach ($testimonios as $testimonio) {
                $calificacion = $testimonio->calificacion_estrella;
                if (isset($distribucion[$calificacion])) {
                    $distribucion[$calificacion]++;
                }
                $sumaCalificaciones += $calificacion;
            }
            
            $promedio = $total > 0 ? $sumaCalificaciones / $total : 0;
            
            // Porcentaje de clientes que recomiendan (4 y 5 estrellas)
            $recomiendan = ($distribucion[5] + $distribucion[4]);
            $porcentajeRecomiendan = $total > 0 ? ($recomiendan / $total) * 100 : 0;
            
            return response()->json([
                'success' => true,
                'data' => [
                    'total' => $total,
                    'promedio' => round($promedio, 1),
                    'porcentajeRecomiendan' => round($porcentajeRecomiendan, 1),
                    'distribucion' => $distribucion
                ]
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error al obtener estadísticas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas'
            ], 500);
        }
    }
    
    /**
     * Verificar si el usuario tiene reparaciones pendientes de calificar
     * GET /api/testimonios/verificar-pendientes
     */
  public function verificarPendientes(): JsonResponse
{
    try {
        \Log::info('🔍 [TESTIMONIO] Iniciando verificarPendientes');
        
        // ✅ Obtener cliente autenticado con JWT
        $cliente = null;
        
        try {
            if (auth('cliente')->check()) {
                $cliente = auth('cliente')->user();
            }
            
            if (!$cliente) {
                $payload = \Tymon\JWTAuth\Facades\JWTAuth::parseToken()->getPayload();
                $clienteId = $payload->get('sub');
                $cliente = \App\Models\Cliente::find($clienteId);
            }
            
        } catch (\Exception $e) {
            \Log::warning('Error obteniendo cliente: ' . $e->getMessage());
        }
        
        if (!$cliente) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado'
            ], 401);
        }
        
        $clienteId = $cliente->id_cliente;
        \Log::info('Cliente autenticado ID: ' . $clienteId);
        
        // Buscar reparaciones pendientes de calificar
        $reparacionesPendientes = \App\Models\Reparacion::where('estado', 'TERMINADO')
            ->where(function($query) use ($clienteId) {
                // Caso 1: Reparación directa (tiene id_ingreso)
                $query->whereHas('ingreso.dispositivo', function($q) use ($clienteId) {
                    $q->where('id_cliente', $clienteId);
                })
                // Caso 2: Reparación vía diagnóstico
                ->orWhereHas('diagnostico.ingreso.dispositivo', function($q) use ($clienteId) {
                    $q->where('id_cliente', $clienteId);
                });
            })
            // ✅ AGREGAR FILTRO: El ingreso debe estar RETIRADO
            ->where(function($query) {
                $query->whereHas('ingreso', function($q) {
                    $q->where('estado', 'RETIRADO');
                })->orWhereHas('diagnostico.ingreso', function($q) {
                    $q->where('estado', 'RETIRADO');
                });
            })
            ->where(function($query) {
                $query->whereDoesntHave('testimonio')
                      ->orWhereHas('testimonio', function($q) {
                          $q->where('skip_testimonio', false)
                            ->where(function($inner) {
                                $inner->whereNull('calificacion_estrella')
                                      ->orWhere('calificacion_estrella', 0);
                            });
                      });
            })
            ->with([
                'ingreso.dispositivo.modelo.marca',
                'diagnostico.ingreso.dispositivo.modelo.marca',
                'testimonio'
            ])
            ->get();
        
        \Log::info('✅ [TESTIMONIO] Reparaciones encontradas', [
            'total' => $reparacionesPendientes->count(),
            'ids' => $reparacionesPendientes->pluck('id_reparacion')->toArray()
        ]);
        
        $resultado = $reparacionesPendientes->map(function($reparacion) {
            $ingreso = null;
            if ($reparacion->id_ingreso) {
                $ingreso = $reparacion->ingreso;
            } elseif ($reparacion->id_diagnostico && $reparacion->diagnostico) {
                $ingreso = $reparacion->diagnostico->ingreso;
            }
            
            $dispositivo = $ingreso?->dispositivo;
            $marca = $dispositivo?->modelo?->marca?->marca ?? '';
            $modelo = $dispositivo?->modelo?->nombre_modelo ?? '';
            $nombreDispositivo = trim($marca . ' ' . $modelo);
            
            return [
                'id_reparacion' => $reparacion->id_reparacion,
                'dispositivo' => $nombreDispositivo ?: 'Dispositivo #' . $reparacion->id_reparacion,
                'fecha_retiro' => $ingreso?->updated_at?->format('d/m/Y') ?? date('d/m/Y'),
                   'es_garantia' => (bool) $reparacion->es_garantia
            ];
        });
        
        return response()->json([
            'success' => true,
            'tiene_pendientes' => $resultado->count() > 0,
            'reparaciones' => $resultado,
            'total' => $resultado->count()
        ]);
        
    } catch (\Exception $e) {
        \Log::error('❌ Error en verificarPendientes: ' . $e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Error al verificar testimonios: ' . $e->getMessage()
        ], 500);
    }
}
    /**
     * Guardar un nuevo testimonio
     * POST /api/testimonios
     */
    public function store(StoreTestimonioRequest $request): JsonResponse
    {
        try {
            $testimonio = Testimonio::updateOrCreate(
                ['id_reparacion' => $request->id_reparacion],
                [
                    'calificacion_estrella' => $request->calificacion_estrella,
                    'comentario' => $request->comentario,
                    'estado' => 'PENDIENTE',
                    'skip_testimonio' => false
                ]
            );
            
            return response()->json([
                'success' => true,
                'message' => '¡Gracias por tu calificación! Tu opinión será revisada por nuestro equipo.',
                'data' => $testimonio
            ], 201);
            
        } catch (\Exception $e) {
            \Log::error('Error al guardar testimonio: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar tu calificación. Intenta de nuevo.'
            ], 500);
        }
    }
    
    /**
     * Guardar "no volver a preguntar" para una reparación
     * POST /api/testimonios/skip
     */
    public function setSkip(UpdateSkipTestimonioRequest $request): JsonResponse
    {
        try {
            $clienteId = auth()->id();
            
            // Verificar que la reparación pertenezca al cliente a través del dispositivo
            $reparacion = Reparacion::where('id_reparacion', $request->id_reparacion)
                ->where(function($query) use ($clienteId) {
                    $query->whereHas('ingreso.dispositivo', function($q) use ($clienteId) {
                        $q->where('id_cliente', $clienteId);
                    })->orWhereHas('diagnostico.ingreso.dispositivo', function($q) use ($clienteId) {
                        $q->where('id_cliente', $clienteId);
                    });
                })
                ->first();
            
            if (!$reparacion) {
                return response()->json([
                    'success' => false,
                    'message' => 'Reparación no encontrada'
                ], 404);
            }
            
            $testimonio = Testimonio::where('id_reparacion', $request->id_reparacion)->first();
            
            if ($testimonio) {
                $testimonio->skip_testimonio = $request->skip_testimonio;
                $testimonio->save();
            } else {
                Testimonio::create([
                    'id_reparacion' => $request->id_reparacion,
                    'calificacion_estrella' => null,
                    'comentario' => null,
                    'estado' => 'PENDIENTE',
                    'skip_testimonio' => $request->skip_testimonio
                ]);
            }
            
            $mensaje = $request->skip_testimonio 
                ? 'No volveremos a preguntarte sobre esta reparación.'
                : 'Te recordaremos más tarde.';
            
            return response()->json([
                'success' => true,
                'message' => $mensaje
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error al guardar skip: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar tu preferencia'
            ], 500);
        }
    }
    
    /**
     * ============================================
     * PARA ADMINISTRADORES (MODERACIÓN)
     * ============================================
     */
    
    /**
     * Obtener todos los testimonios (para moderar)
     * GET /api/admin/testimonios
     */
    public function indexAdmin(): JsonResponse
    {
        try {
            $testimonios = Testimonio::with('reparacion')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function($testimonio) {
                    return [
                        'id' => $testimonio->id_testimonio,
                        'id_reparacion' => $testimonio->id_reparacion,
                        'cliente_nombre' => $testimonio->nombre_cliente,
                        'calificacion' => $testimonio->calificacion_estrella,
                        'comentario' => $testimonio->comentario,
                        'dispositivo' => $testimonio->dispositivo,
                        'estado' => $testimonio->estado,
                        'skip_testimonio' => $testimonio->skip_testimonio,
                        'created_at' => $testimonio->created_at->format('d/m/Y H:i'),
                    ];
                });
            
            return response()->json([
                'success' => true,
                'data' => $testimonios
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error al obtener testimonios para admin: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los testimonios'
            ], 500);
        }
    }
    
    /**
     * Cambiar el estado de un testimonio (APROBAR/RECHAZAR)
     * PUT /api/admin/testimonios/{id}/estado
     */
    public function updateEstado(UpdateTestimonioEstadoRequest $request, $id): JsonResponse
    {
        try {
            $testimonio = Testimonio::find($id);
            
            if (!$testimonio) {
                return response()->json([
                    'success' => false,
                    'message' => 'Testimonio no encontrado'
                ], 404);
            }
            
            $testimonio->estado = $request->estado;
            $testimonio->save();
            
            $mensajes = [
                'APROBADO' => 'Testimonio aprobado y visible en la página.',
                'RECHAZADO' => 'Testimonio rechazado.',
                'PENDIENTE' => 'Testimonio vuelto a estado pendiente.'
            ];
            
            return response()->json([
                'success' => true,
                'message' => $mensajes[$request->estado] ?? 'Estado actualizado correctamente.',
                'data' => $testimonio
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error al actualizar estado: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el estado'
            ], 500);
        }
    }
    
    /**
     * Eliminar un testimonio
     * DELETE /api/admin/testimonios/{id}
     */
    public function destroy($id): JsonResponse
    {
        try {
            $testimonio = Testimonio::find($id);
            
            if (!$testimonio) {
                return response()->json([
                    'success' => false,
                    'message' => 'Testimonio no encontrado'
                ], 404);
            }
            
            $testimonio->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Testimonio eliminado correctamente.'
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error al eliminar testimonio: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el testimonio'
            ], 500);
        }
    }
}