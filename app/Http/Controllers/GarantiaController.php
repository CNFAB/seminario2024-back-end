<?php

namespace App\Http\Controllers;

use App\Models\Garantia;
use App\Models\GarantiaPieza;
use App\Models\ReparacionesMultiple;
use App\Models\Reparacion; 
use App\Models\Ingreso_d;      
use App\Models\Dispositivo;   
use App\Http\Requests\Garantia\StoreGarantiaRequest;
use App\Http\Requests\Garantia\UpdateGarantiaRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GarantiaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
  public function index(): JsonResponse
{
    try {
        $garantias = Garantia::with([
            'reparacion.ingreso.dispositivo.modelo.marca',
            'reparacion.diagnostico.ingreso.dispositivo.modelo.marca',
            'garantiaPiezas.pieza',
            'garantiaPiezas.categoria'
        ])
        ->orderBy('created_at', 'desc')
        ->get();
        
        return response()->json([
            'success' => true,
            'data' => $garantias,
            'message' => 'Garantías obtenidas exitosamente',
            'count' => $garantias->count()
        ], 200);
        
    } catch (\Exception $e) {
        \Log::error('Error al obtener garantías', [
            'error' => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => $e->getFile()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Error al obtener garantías: ' . $e->getMessage(),
            'error' => $e->getMessage()
        ], 500);
    }
}

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreGarantiaRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            
            $data = $request->validated();
            
            // Crear la garantía
            $garantia = Garantia::create($data);
            
            // Generar automáticamente las garantías por pieza
            $this->generarGarantiaPiezas($garantia);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'data' => $garantia->load(['garantiaPiezas.pieza', 'garantiaPiezas.categoria', 'reparacion']),
                'message' => 'Garantía creada exitosamente'
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la garantía',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
  public function show(int $id): JsonResponse
{
    try {
        $garantia = Garantia::with([
            'reparacion.diagnostico.ingreso.dispositivo.modelo.marca',
            'reparacion.ingreso.dispositivo.modelo.marca',
            'garantiaPiezas.pieza',
            'garantiaPiezas.categoria'
        ])->find($id);
        
        if (!$garantia) {
            return response()->json([
                'success' => false,
                'message' => 'Garantía no encontrada'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => $garantia,
            'message' => 'Garantía obtenida exitosamente'
        ], 200);
        
    } catch (\Exception $e) {
        \Log::error('Error al obtener garantía', [
            'id' => $id,
            'error' => $e->getMessage()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Error al obtener la garantía',
            'error' => $e->getMessage()
        ], 500);
    }
}

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateGarantiaRequest $request, int $id): JsonResponse
    {
        try {
            DB::beginTransaction();
            
            $garantia = Garantia::find($id);
            
            if (!$garantia) {
                return response()->json([
                    'success' => false,
                    'message' => 'Garantía no encontrada'
                ], 404);
            }
            
            $data = $request->validated();
            $garantia->update($data);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'data' => $garantia->fresh(['garantiaPiezas.pieza', 'garantiaPiezas.categoria']),
                'message' => 'Garantía actualizada exitosamente'
            ], 200);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la garantía',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            DB::beginTransaction();
            
            $garantia = Garantia::find($id);
            
            if (!$garantia) {
                return response()->json([
                    'success' => false,
                    'message' => 'Garantía no encontrada'
                ], 404);
            }
            
            // Eliminar primero las garantías por pieza
            $garantia->garantiaPiezas()->delete();
            $garantia->delete();
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Garantía eliminada exitosamente'
            ], 200);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la garantía',
                'error' => $e->getMessage()
            ], 500);
        }
    }
public function porVencer(Request $request): JsonResponse
{
    try {
        $dias = (int) $request->get('dias', 30); // ✅ Convertir a entero
        
        $garantias = Garantia::where('estado', 'ACTIVA')
            ->where('fecha_fin', '<=', now()->addDays($dias))
            ->where('fecha_fin', '>=', now())
            ->with([
                'reparacion.ingreso.dispositivo.modelo.marca',
                'reparacion.ingreso.dispositivo.cliente',
                'reparacion.diagnostico.ingreso.dispositivo.modelo.marca',
                'reparacion.diagnostico.ingreso.dispositivo.cliente'
            ])
            ->orderBy('fecha_fin', 'asc')
            ->get();
        
        // Formatear datos para el frontend
        $resultado = $garantias->map(function($garantia) {
            // Obtener cliente desde la reparación
            $reparacion = $garantia->reparacion;
            $cliente = null;
            $dispositivoNombre = 'N/A';
            
            if ($reparacion) {
                // Intentar desde ingreso directo
                if ($reparacion->ingreso && $reparacion->ingreso->dispositivo) {
                    $dispositivo = $reparacion->ingreso->dispositivo;
                    $cliente = $dispositivo->cliente;
                    $dispositivoNombre = $dispositivo->modelo 
                        ? trim($dispositivo->modelo->marca->marca . ' ' . $dispositivo->modelo->nombre_modelo)
                        : 'Dispositivo';
                }
                // Intentar desde diagnóstico
                elseif ($reparacion->diagnostico && $reparacion->diagnostico->ingreso && $reparacion->diagnostico->ingreso->dispositivo) {
                    $dispositivo = $reparacion->diagnostico->ingreso->dispositivo;
                    $cliente = $dispositivo->cliente;
                    $dispositivoNombre = $dispositivo->modelo 
                        ? trim($dispositivo->modelo->marca->marca . ' ' . $dispositivo->modelo->nombre_modelo)
                        : 'Dispositivo';
                }
            }
            
            // Calcular días restantes
            $fechaFin = \Carbon\Carbon::parse($garantia->fecha_fin);
            $diasRestantes = $fechaFin->diffInDays(now(), false);
            
            return [
                'id_garantia' => $garantia->id_garantia,
                'fecha_inicio' => $garantia->fecha_inicio,
                'fecha_fin' => $garantia->fecha_fin,
                'estado' => $garantia->estado,
                'cliente_nombre' => $cliente->nombre ?? 'N/A',
                'cliente_apellido' => $cliente->apellido ?? '',
                'dispositivo_nombre' => $dispositivoNombre,
                'dias_restantes' => max(0, $diasRestantes), // ✅ Asegurar que no sea negativo
            ];
        });
        
        return response()->json([
            'success' => true,
            'data' => $resultado,
            'total' => $resultado->count(),
            'dias' => $dias,
            'message' => "Garantías que vencen en los próximos {$dias} días"
        ], 200);
        
    } catch (\Exception $e) {
        \Log::error('Error al obtener garantías por vencer', [
            'error' => $e->getMessage(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Error al obtener garantías por vencer: ' . $e->getMessage()
        ], 500);
    }
}

    // ==========================================
    // MÉTODOS ADICIONALES
    // ==========================================

    /**
     * Generar automáticamente las garantías por pieza
     */
    private function generarGarantiaPiezas(Garantia $garantia): void
    {
        // Obtener todas las piezas de la reparación
        $reparacionMultiples = ReparacionesMultiple::where('id_reparacion', $garantia->id_reparacion)
            ->with(['pieza.categoria'])
            ->get();
        
        foreach ($reparacionMultiples as $rm) {
            $pieza = $rm->pieza;
            $categoria = $pieza ? $pieza->categoria : null;
            
            // Obtener días de garantía de la categoría (por defecto 90)
            $diasGarantia = $categoria && $categoria->garantia_dias ? $categoria->garantia_dias : 90;
            
            // Calcular fecha_fin
            $fechaInicio = \Carbon\Carbon::parse($garantia->fecha_inicio);
            $fechaFin = $fechaInicio->copy()->addDays($diasGarantia);
            
            // Crear garantía por pieza
            GarantiaPieza::create([
                'id_garantia' => $garantia->id_garantia,
                'id_reparacion_multiple' => $rm->id_multiple,
                'id_pieza' => $pieza ? $pieza->id_pieza : null,
                'id_categoria' => $categoria ? $categoria->id_categoria : null,
                'garantia_dias_asignados' => $diasGarantia,
                'fecha_inicio' => $garantia->fecha_inicio,
                'fecha_fin' => $fechaFin,
                'estado' => 'ACTIVA'
            ]);
        }
        
        // Actualizar la garantía principal con la fecha_fin más larga
        $fechaFinMax = $garantia->garantiaPiezas()->max('fecha_fin');
        if ($fechaFinMax) {
            $garantia->fecha_fin = $fechaFinMax;
            $garantia->duracion_meses = \Carbon\Carbon::parse($garantia->fecha_inicio)->diffInMonths($fechaFinMax);
            $garantia->save();
        }
    }

    /**
     * Obtener garantías por cliente
     */
    /**
 * Obtener garantías por cliente
 */
/**
 * Obtener garantías por cliente
 */
/**
 * Obtener evolución mensual por semestre
 */
public function evolucionMensual(Request $request): JsonResponse
{
    try {
        $semestre = $request->get('semestre', 'ene-jun'); // ene-jun o jul-dic
        $year = (int) $request->get('year', now()->year);
        
        // Definir meses según semestre
        if ($semestre === 'ene-jun') {
            $meses = [1, 2, 3, 4, 5, 6];
            $nombreMeses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun'];
        } else {
            $meses = [7, 8, 9, 10, 11, 12];
            $nombreMeses = ['Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        }
        
        $resultado = [];
        foreach ($meses as $index => $mes) {
            $inicio = \Carbon\Carbon::create($year, $mes, 1)->startOfMonth();
            $fin = \Carbon\Carbon::create($year, $mes, 1)->endOfMonth();
            
            $garantias = Garantia::whereBetween('fecha_inicio', [$inicio, $fin])->get();
            
            $resultado[] = [
                'mes' => $nombreMeses[$index],
                'emitidas' => $garantias->count(),
                'vencidas' => $garantias->where('estado', 'VENCIDA')->count(),
                'reclamadas' => $garantias->where('estado', 'RECLAMADA')->count(),
                'activas' => $garantias->where('estado', 'ACTIVA')->count()
            ];
        }
        
        return response()->json([
            'success' => true,
            'data' => $resultado,
            'semestre' => $semestre,
            'year' => $year
        ], 200);
        
    } catch (\Exception $e) {
        \Log::error('Error en evolucionMensual', [
            'error' => $e->getMessage(),
            'line' => $e->getLine()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Error al obtener evolución mensual'
        ], 500);
    }
}
/**
 * Obtener estadísticas de control de calidad
 */
public function controlCalidad(): JsonResponse
{
    try {
        // 1. COSTO TOTAL PERDIDO POR GARANTÍAS
        $costoTotalGarantias = Reparacion::where('es_garantia', true)
            ->with(['reparacionesMultiples'])
            ->get()
            ->sum(function($reparacion) {
                return $reparacion->reparacionesMultiples->sum('precio_total');
            });

        // 2. COSTO POR MES (últimos 6 meses)
        $costoPorMes = [];
        for ($i = 5; $i >= 0; $i--) {
            $mes = now()->subMonths($i);
            $inicio = $mes->copy()->startOfMonth();
            $fin = $mes->copy()->endOfMonth();
            
            $costo = Reparacion::where('es_garantia', true)
                ->whereBetween('created_at', [$inicio, $fin])
                ->with(['reparacionesMultiples'])
                ->get()
                ->sum(function($reparacion) {
                    return $reparacion->reparacionesMultiples->sum('precio_total');
                });
            
            $costoPorMes[] = [
                'mes' => $mes->format('M'),
                'costo' => round($costo, 2)
            ];
        }

        // 3. PIEZAS QUE MÁS FALLAN (las que más aparecen en reparaciones de garantía)
        $piezasMasFallan = Reparacion::where('es_garantia', true)
            ->with(['reparacionesMultiples.pieza.categoria'])
            ->get()
            ->flatMap(function($reparacion) {
                return $reparacion->reparacionesMultiples;
            })
            ->groupBy('id_pieza')
            ->map(function($items, $piezaId) {
                $primera = $items->first();
                $pieza = $primera->pieza;
                
                return [
                    'id_pieza' => $piezaId,
                    'nombre_pieza' => $pieza->nombre_pieza ?? 'Pieza eliminada',
                    'categoria' => $pieza->categoria->categoria ?? 'N/A',
                    'cantidad_fallas' => $items->count(),
                    'costo_total' => round($items->sum('precio_total'), 2),
                    'costo_promedio' => round($items->avg('precio_total'), 2),
                ];
            })
            ->sortByDesc('cantidad_fallas')
            ->values()
            ->take(10);

        // Calcular porcentajes
        $totalFallas = $piezasMasFallan->sum('cantidad_fallas');
        $piezasMasFallan = $piezasMasFallan->map(function($item) use ($totalFallas) {
            $item['porcentaje'] = $totalFallas > 0 ? round(($item['cantidad_fallas'] / $totalFallas) * 100, 1) : 0;
            return $item;
        });

        // 4. MODELOS/DISPOSITIVOS QUE MÁS FALLAN
        $modelosMasFallan = Reparacion::where('es_garantia', true)
            ->with(['ingreso.dispositivo.modelo.marca', 'diagnostico.ingreso.dispositivo.modelo.marca'])
            ->get()
            ->map(function($reparacion) {
                $dispositivo = $reparacion->ingreso?->dispositivo 
                    ?? $reparacion->diagnostico?->ingreso?->dispositivo;
                return $dispositivo;
            })
            ->filter()
            ->groupBy('id_modelo')
            ->map(function($items, $modeloId) {
                $primera = $items->first();
                $modelo = $primera->modelo;
                
                return [
                    'id_modelo' => $modeloId,
                    'marca' => $modelo->marca->marca ?? 'N/A',
                    'modelo' => $modelo->nombre_modelo ?? 'N/A',
                    'cantidad_fallas' => $items->count(),
                ];
            })
            ->sortByDesc('cantidad_fallas')
            ->take(10)
            ->values();

        $totalFallasModelos = $modelosMasFallan->sum('cantidad_fallas');
        $modelosMasFallan = $modelosMasFallan->map(function($item) use ($totalFallasModelos) {
            $item['porcentaje'] = $totalFallasModelos > 0 ? round(($item['cantidad_fallas'] / $totalFallasModelos) * 100, 1) : 0;
            return $item;
        });

        // 5. CLIENTES CON MÁS RECLAMOS (reincidencia)
        $clientesReincidentes = Reparacion::where('es_garantia', true)
            ->with(['ingreso.dispositivo.cliente', 'diagnostico.ingreso.dispositivo.cliente'])
            ->get()
            ->map(function($reparacion) {
                $cliente = $reparacion->ingreso?->dispositivo?->cliente 
                    ?? $reparacion->diagnostico?->ingreso?->dispositivo?->cliente;
                return $cliente;
            })
            ->filter()
            ->groupBy('id_cliente')
            ->map(function($items, $clienteId) {
                $primera = $items->first();
                
                return [
                    'id_cliente' => $clienteId,
                    'nombre' => $primera->nombre ?? 'N/A',
                    'apellido' => $primera->apellido ?? '',
                    'correo' => $primera->correo ?? 'N/A',
                    'telefono' => $primera->numero_celular ?? 'N/A',
                    'total_garantias' => $items->count(),
                    'nivel_reincidencia' => $items->count() >= 3 ? 'ALTA' : ($items->count() >= 2 ? 'MEDIA' : 'BAJA')
                ];
            })
            ->sortByDesc('total_garantias')
            ->take(10)
            ->values();

        // 6. Resumen ejecutivo
        $resumen = [
            'costo_total_perdido' => round($costoTotalGarantias, 2),
            'total_reparaciones_garantia' => Reparacion::where('es_garantia', true)->count(),
            'costo_promedio_por_garantia' => $costoTotalGarantias > 0 
                ? round($costoTotalGarantias / Reparacion::where('es_garantia', true)->count(), 2)
                : 0,
            'pieza_que_mas_falla' => $piezasMasFallan->first()['nombre_pieza'] ?? 'N/A',
            'modelo_que_mas_falla' => ($modelosMasFallan->first()['marca'] ?? '') . ' ' . ($modelosMasFallan->first()['modelo'] ?? ''),
            'cliente_con_mas_garantias' => ($clientesReincidentes->first()['nombre'] ?? '') . ' ' . ($clientesReincidentes->first()['apellido'] ?? ''),
            'clientes_con_alta_reincidencia' => $clientesReincidentes->where('nivel_reincidencia', 'ALTA')->count()
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'resumen' => $resumen,
                'costo_por_mes' => $costoPorMes,
                'piezas_mas_fallan' => $piezasMasFallan,
                'modelos_mas_fallan' => $modelosMasFallan,
                'clientes_reincidentes' => $clientesReincidentes
            ]
        ], 200);

    } catch (\Exception $e) {
        \Log::error('Error en controlCalidad', [
            'error' => $e->getMessage(),
            'line' => $e->getLine()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Error al obtener estadísticas de control de calidad: ' . $e->getMessage()
        ], 500);
    }
}
public function porCliente(int $idCliente): JsonResponse
{
    try {
        // Obtener dispositivos del cliente
        $dispositivos = Dispositivo::where('id_cliente', $idCliente)->pluck('id_dispositivo');
        
        // Obtener ingresos de esos dispositivos
        $ingresos = Ingreso_d::whereIn('id_dispositivo', $dispositivos)->pluck('id_ingreso');
        
        // 👉 Buscar reparaciones tanto por ingreso directo como por diagnóstico
        $reparaciones = Reparacion::where(function($q) use ($ingresos) {
                // Reparaciones con ingreso directo (reparaciones directas)
                $q->whereIn('id_ingreso', $ingresos);
                
                // Reparaciones desde diagnóstico
                $q->orWhereHas('diagnostico', function($sub) use ($ingresos) {
                    $sub->whereIn('id_ingreso', $ingresos);
                });
            })
            ->pluck('id_reparacion');
        
        // Obtener garantías
        $garantias = Garantia::whereIn('id_reparacion', $reparaciones)
            ->with([
                'reparacion.ingreso.dispositivo.cliente',
                'reparacion.diagnostico.ingreso.dispositivo.cliente',
                'garantiaPiezas.pieza',
                'garantiaPiezas.categoria'
            ])
            ->orderBy('fecha_fin', 'desc')
            ->get();
        
        return response()->json([
            'success' => true,
            'data' => $garantias,
            'message' => 'Garantías del cliente obtenidas exitosamente',
            'count' => $garantias->count()
        ], 200);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error al obtener garantías del cliente',
            'error' => $e->getMessage()
        ], 500);
    }
}


public function porDispositivo(int $idDispositivo): JsonResponse
{
    try {
        // Obtener garantías a través de la relación reparación → ingreso → dispositivo
        $garantias = Garantia::whereHas('reparacion', function($q) use ($idDispositivo) {
                $q->whereHas('ingreso', function($sub) use ($idDispositivo) {
                    $sub->where('id_dispositivo', $idDispositivo);
                })
                ->orWhereHas('diagnostico.ingreso', function($sub) use ($idDispositivo) {
                    $sub->where('id_dispositivo', $idDispositivo);
                });
            })
            ->with(['garantiaPiezas.pieza', 'garantiaPiezas.categoria'])
            ->orderBy('created_at', 'desc')
            ->get();
        
        return response()->json([
            'success' => true,
            'data' => $garantias,
            'message' => 'Garantías del dispositivo obtenidas exitosamente',
            'count' => $garantias->count()
        ], 200);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error al obtener garantías del dispositivo',
            'error' => $e->getMessage()
        ], 500);
    }
}

    /**
     * Obtener garantías activas
     */
    public function activas(): JsonResponse
    {
        try {
            $garantias = Garantia::activas()
                ->with([
                    'reparacion.dispositivo.cliente',
                    'garantiaPiezas.pieza',
                    'garantiaPiezas.categoria'
                ])
                ->orderBy('fecha_fin', 'asc')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $garantias,
                'message' => 'Garantías activas obtenidas exitosamente',
                'count' => $garantias->count()
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener garantías activas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener garantías vencidas
     */
    public function vencidas(): JsonResponse
    {
        try {
            $garantias = Garantia::vencidas()
                ->with([
                    'reparacion.dispositivo.cliente',
                    'garantiaPiezas.pieza'
                ])
                ->orderBy('fecha_fin', 'desc')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $garantias,
                'message' => 'Garantías vencidas obtenidas exitosamente',
                'count' => $garantias->count()
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener garantías vencidas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verificar si una reparación tiene garantía activa
     */
    public function verificarPorReparacion(int $idReparacion): JsonResponse
    {
        try {
            $garantia = Garantia::where('id_reparacion', $idReparacion)
                ->activas()
                ->first();
            
            // Si tiene garantía activa, obtener también las piezas cubiertas
            $piezasCubiertas = [];
            if ($garantia) {
                $piezasCubiertas = $garantia->garantiaPiezas()
                    ->where('fecha_fin', '>=', now())
                    ->with('pieza','categoria')
                    ->get();
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'tiene_garantia_activa' => !is_null($garantia),
                    'garantia' => $garantia,
                    'piezas_cubiertas' => $piezasCubiertas,
                    'dias_restantes' => $garantia ? $garantia->dias_restantes : 0,
                    'porcentaje_restante' => $garantia ? $garantia->porcentaje_restante : 0
                ],
                'message' => $garantia ? 'La reparación tiene garantía activa' : 'La reparación no tiene garantía activa'
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al verificar garantía',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener resumen de garantías (para dashboard)
     */
    public function resumen(): JsonResponse
    {
        try {
            $total = Garantia::count();
            $activas = Garantia::activas()->count();
            $vencidas = Garantia::vencidas()->count();
            $reclamadas = Garantia::where('estado', 'RECLAMADA')->count();
            
            // Garantías que vencen en los próximos 30 días
            $porVencer = Garantia::activas()
                ->where('fecha_fin', '<=', now()->addDays(30))
                ->count();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'total' => $total,
                    'activas' => $activas,
                    'vencidas' => $vencidas,
                    'reclamadas' => $reclamadas,
                    'por_vencer' => $porVencer,
                    'porcentaje_activas' => $total > 0 ? round(($activas / $total) * 100, 2) : 0
                ],
                'message' => 'Resumen de garantías obtenido exitosamente'
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener resumen de garantías',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Regenerar garantías por pieza para una garantía existente
     * (Útil si se agregaron piezas después)
     */
    public function regenerarPiezas(int $id): JsonResponse
    {
        try {
            DB::beginTransaction();
            
            $garantia = Garantia::find($id);
            
            if (!$garantia) {
                return response()->json([
                    'success' => false,
                    'message' => 'Garantía no encontrada'
                ], 404);
            }
            
            // Eliminar garantías por pieza existentes
            $garantia->garantiaPiezas()->delete();
            
            // Regenerar
            $this->generarGarantiaPiezas($garantia);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'data' => $garantia->load(['garantiaPiezas.pieza', 'garantiaPiezas.categoria']),
                'message' => 'Garantías por pieza regeneradas exitosamente'
            ], 200);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al regenerar garantías por pieza',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}