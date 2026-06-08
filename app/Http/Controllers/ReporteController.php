<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\ReparacionMultiple;
use App\Models\Reparacion;
use App\Models\Diagnostico;
use App\Models\Pieza;
use App\Models\Usuario;
use Carbon\Carbon;

class ReporteController extends Controller
{
    // ============================================
    // PARTE 1: ESTADÍSTICAS PARA DASHBOARD
    // ============================================

    /**
     * Estadísticas generales del dashboard
     * GET /api/reportes/dashboard
     */
    public function dashboard()
    {
        try {
            $hoy = Carbon::today();
            $mesActual = Carbon::now()->month;
            $mesAnterior = Carbon::now()->subMonth()->month;
            $añoActual = Carbon::now()->year;
            $añoAnterior = Carbon::now()->subMonth()->year;

            // 1. Resumen general
            $resumen = [
                'total_piezas' => Pieza::count(),
                'total_tecnicos' => Usuario::where('es_tecnico', true)->count(),
                'total_recepcionistas' => Usuario::where('es_recepcionista', true)->count(),
                'reparaciones_hoy' => ReparacionMultiple::whereDate('fecha_fin_reparacion', $hoy)
                    ->where('estado', 'TERMINADO')
                    ->count(),
            ];

            // 2. Ingresos del mes actual vs mes anterior
            $ingresosMesActual = ReparacionMultiple::whereMonth('fecha_fin_reparacion', $mesActual)
                ->whereYear('fecha_fin_reparacion', $añoActual)
                ->where('estado', 'TERMINADO')
                ->sum('precio_total');

            $ingresosMesAnterior = ReparacionMultiple::whereMonth('fecha_fin_reparacion', $mesAnterior)
                ->whereYear('fecha_fin_reparacion', $añoAnterior)
                ->where('estado', 'TERMINADO')
                ->sum('precio_total');

            // 3. Reparaciones por estado
            $reparacionesPorEstado = ReparacionMultiple::select('estado', DB::raw('count(*) as total'))
                ->groupBy('estado')
                ->get()
                ->pluck('total', 'estado');

            // 4. Top 5 técnicos del mes
            $topTecnicos = DB::table('reparacion_multiple')
                ->join('reparacion', 'reparacion_multiple.id_reparacion', '=', 'reparacion.id_reparacion')
                ->join('usuario', 'reparacion.id_usuario', '=', 'usuario.id_usuario')
                ->whereMonth('reparacion_multiple.fecha_fin_reparacion', $mesActual)
                ->whereYear('reparacion_multiple.fecha_fin_reparacion', $añoActual)
                ->where('reparacion_multiple.estado', 'TERMINADO')
                ->select(
                    'usuario.id_usuario',
                    'usuario.nombre',
                    'usuario.apellido',
                    DB::raw('COUNT(*) as total_reparaciones'),
                    DB::raw('SUM(reparacion_multiple.precio_total) as total_ingresos')
                )
                ->groupBy('usuario.id_usuario', 'usuario.nombre', 'usuario.apellido')
                ->orderByDesc('total_reparaciones')
                ->limit(5)
                ->get();

            // 5. Piezas más usadas del mes
            $piezasMasUsadas = DB::table('reparacion_multiple')
                ->join('pieza', 'reparacion_multiple.id_pieza', '=', 'pieza.id_pieza')
                ->whereMonth('reparacion_multiple.fecha_fin_reparacion', $mesActual)
                ->whereYear('reparacion_multiple.fecha_fin_reparacion', $añoActual)
                ->where('reparacion_multiple.estado', 'TERMINADO')
                ->select(
                    'pieza.id_pieza',
                    'pieza.nombre_pieza',
                    DB::raw('COUNT(*) as veces_usada'),
                    DB::raw('SUM(reparacion_multiple.precio_total) as total_generado')
                )
                ->groupBy('pieza.id_pieza', 'pieza.nombre_pieza')
                ->orderByDesc('veces_usada')
                ->limit(5)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'resumen' => $resumen,
                    'ingresos' => [
                        'mes_actual' => round($ingresosMesActual, 2),
                        'mes_anterior' => round($ingresosMesAnterior, 2),
                        'diferencia' => round($ingresosMesActual - $ingresosMesAnterior, 2),
                        'porcentaje_cambio' => $this->calcularPorcentaje($ingresosMesAnterior, $ingresosMesActual)
                    ],
                    'reparaciones_por_estado' => $reparacionesPorEstado,
                    'top_tecnicos' => $topTecnicos,
                    'piezas_mas_usadas' => $piezasMasUsadas,
                    'periodo' => [
                        'mes_actual' => Carbon::now()->format('F Y'),
                        'mes_anterior' => Carbon::now()->subMonth()->format('F Y')
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ============================================
    // PARTE 2: REPORTES CON FILTROS
    // ============================================

    /**
     * Reporte de ingresos por período
     * POST /api/reportes/ingresos
     */
    public function reparacionesActivasPorTecnico($idTecnico): JsonResponse
{
    try {
        $reparaciones = Reparacion::where('id_usuario', $idTecnico)
            // Sin piezas trabajadas
            ->whereDoesntHave('reparacionesMultiples', function($q) {
                $q->whereIn('estado', ['EN_REPARACION', 'ESPERANDO_PIEZA', 'TERMINADO', 
                                       'CANCELADO', 'RECHAZADO', 'ESPERANDO_APROBACION']);
            })
            ->with([
                'ingreso:id_ingreso,fecha_ingreso,id_dispositivo',
                'ingreso.dispositivo:id_dispositivo,id_modelo',
                'ingreso.dispositivo.modelo:id_modelo,nombre_modelo,id_marca',
                'ingreso.dispositivo.modelo.marca:id_marca,marca',
                'reparacionesMultiples:id_multiple,id_reparacion,estado,id_pieza',
                'reparacionesMultiples.pieza:id_pieza,nombre_pieza',
            ])
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $reparaciones
        ]);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
    }
}
    public function ingresosPorPeriodo(Request $request)
    {
        try {
            $request->validate([
                'fecha_inicio' => 'required|date',
                'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
            ]);

            $fechaInicio = Carbon::parse($request->fecha_inicio)->startOfDay();
            $fechaFin = Carbon::parse($request->fecha_fin)->endOfDay();

            // 1. Resumen general
            $resumen = [
                'total_ingresos' => 0,
                'total_reparaciones' => 0,
                'promedio_por_reparacion' => 0,
                'tecnicos_activos' => 0,
                'dias_en_periodo' => $fechaInicio->diffInDays($fechaFin) + 1
            ];

            // 2. Ingresos por día (para gráfica)
            $ingresosPorDia = ReparacionMultiple::whereBetween('fecha_fin_reparacion', [$fechaInicio, $fechaFin])
                ->where('estado', 'TERMINADO')
                ->select(
                    DB::raw('DATE(fecha_fin_reparacion) as fecha'),
                    DB::raw('SUM(precio_total) as total'),
                    DB::raw('COUNT(*) as cantidad')
                )
                ->groupBy(DB::raw('DATE(fecha_fin_reparacion)'))
                ->orderBy('fecha')
                ->get();

            // 3. Ingresos por técnico
            $ingresosPorTecnico = DB::table('reparacion_multiple')
                ->join('reparacion', 'reparacion_multiple.id_reparacion', '=', 'reparacion.id_reparacion')
                ->join('usuario', 'reparacion.id_usuario', '=', 'usuario.id_usuario')
                ->whereBetween('reparacion_multiple.fecha_fin_reparacion', [$fechaInicio, $fechaFin])
                ->where('reparacion_multiple.estado', 'TERMINADO')
                ->select(
                    'usuario.id_usuario',
                    'usuario.nombre',
                    'usuario.apellido',
                    DB::raw('SUM(reparacion_multiple.precio_total) as total_ingresos'),
                    DB::raw('COUNT(*) as total_reparaciones'),
                    DB::raw('AVG(reparacion_multiple.precio_total) as promedio')
                )
                ->groupBy('usuario.id_usuario', 'usuario.nombre', 'usuario.apellido')
                ->orderByDesc('total_ingresos')
                ->get();

            // 4. Piezas más usadas en el período
            $piezasMasUsadas = DB::table('reparacion_multiple')
                ->join('pieza', 'reparacion_multiple.id_pieza', '=', 'pieza.id_pieza')
                ->join('categoria', 'pieza.id_categoria', '=', 'categoria.id_categoria')
                ->whereBetween('reparacion_multiple.fecha_fin_reparacion', [$fechaInicio, $fechaFin])
                ->where('reparacion_multiple.estado', 'TERMINADO')
                ->select(
                    'pieza.id_pieza',
                    'pieza.nombre_pieza',
                    'categoria.categoria',
                    DB::raw('COUNT(*) as veces_usada'),
                    DB::raw('SUM(reparacion_multiple.precio_total) as total_generado')
                )
                ->groupBy('pieza.id_pieza', 'pieza.nombre_pieza', 'categoria.categoria')
                ->orderByDesc('veces_usada')
                ->limit(10)
                ->get();

            // 5. Calcular resumen
            $resumen['total_ingresos'] = round($ingresosPorDia->sum('total'), 2);
            $resumen['total_reparaciones'] = $ingresosPorDia->sum('cantidad');
            $resumen['promedio_por_reparacion'] = $resumen['total_reparaciones'] > 0 
                ? round($resumen['total_ingresos'] / $resumen['total_reparaciones'], 2) 
                : 0;
            $resumen['tecnicos_activos'] = $ingresosPorTecnico->count();

            // 6. Comparativa con período anterior
            $diasPeriodo = $fechaInicio->diffInDays($fechaFin);
            $fechaInicioAnterior = (clone $fechaInicio)->subDays($diasPeriodo + 1);
            $fechaFinAnterior = (clone $fechaInicio)->subDay();

            $ingresosPeriodoAnterior = ReparacionMultiple::whereBetween('fecha_fin_reparacion', [$fechaInicioAnterior, $fechaFinAnterior])
                ->where('estado', 'TERMINADO')
                ->sum('precio_total');

            $reparacionesPeriodoAnterior = ReparacionMultiple::whereBetween('fecha_fin_reparacion', [$fechaInicioAnterior, $fechaFinAnterior])
                ->where('estado', 'TERMINADO')
                ->count();

            $variacion = $ingresosPeriodoAnterior > 0 
                ? round((($resumen['total_ingresos'] - $ingresosPeriodoAnterior) / $ingresosPeriodoAnterior) * 100, 2)
                : 100;

            return response()->json([
                'success' => true,
                'data' => [
                    'periodo' => [
                        'inicio' => $fechaInicio->format('Y-m-d'),
                        'fin' => $fechaFin->format('Y-m-d'),
                        'dias' => $resumen['dias_en_periodo']
                    ],
                    'resumen' => $resumen,
                    'ingresos_por_dia' => $ingresosPorDia,
                    'ingresos_por_tecnico' => $ingresosPorTecnico,
                    'piezas_mas_usadas' => $piezasMasUsadas,
                    'comparativa' => [
                        'periodo_anterior' => [
                            'inicio' => $fechaInicioAnterior->format('Y-m-d'),
                            'fin' => $fechaFinAnterior->format('Y-m-d'),
                            'ingresos' => round($ingresosPeriodoAnterior, 2),
                            'reparaciones' => $reparacionesPeriodoAnterior
                        ],
                        'variacion' => $variacion,
                        'tendencia' => $variacion > 0 ? 'positiva' : ($variacion < 0 ? 'negativa' : 'estable')
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar reporte',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reporte por técnico
     * POST /api/reportes/tecnico
     */
   public function reportePorTecnico(Request $request)
{
    try {
        $request->validate([
            'tecnico_id' => 'required|integer|exists:usuario,id_usuario',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
        ]);

        $tecnicoId = $request->tecnico_id;
        $fechaInicio = Carbon::parse($request->fecha_inicio)->startOfDay();
        $fechaFin = Carbon::parse($request->fecha_fin)->endOfDay();

        // Datos del técnico
        $tecnico = Usuario::find($tecnicoId);

        // Reparaciones del técnico en el período
        $reparaciones = ReparacionMultiple::join('reparacion', 'reparacion_multiple.id_reparacion', '=', 'reparacion.id_reparacion')
            ->where('reparacion.id_usuario', $tecnicoId)
            ->whereBetween('reparacion_multiple.fecha_fin_reparacion', [$fechaInicio, $fechaFin])
            ->where('reparacion_multiple.estado', 'TERMINADO')
            ->select(
                'reparacion_multiple.*',
                'reparacion.comentario'
            )
            ->get();

        // Resumen
        $resumen = [
            'total_reparaciones' => $reparaciones->count(),
            'total_ingresos' => round($reparaciones->sum('precio_total'), 2),
            'tiempo_promedio' => $reparaciones->avg('duracion_horas'),
            'piezas_mas_usadas' => $reparaciones->groupBy('id_pieza')
                ->map(function ($group) {
                    $pieza = $group->first()->pieza;
                    return [
                        'pieza' => $pieza->nombre_pieza ?? 'Desconocida',
                        'cantidad' => $group->count(),
                        'total' => round($group->sum('precio_total'), 2)
                    ];
                })
                ->sortByDesc('cantidad')
                ->take(5)
                ->values()
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'tecnico' => [
                    'id' => $tecnico->id_usuario,
                    'nombre' => $tecnico->nombre,
                    'apellido' => $tecnico->apellido
                ],
                'periodo' => [
                    'inicio' => $fechaInicio->format('Y-m-d'),
                    'fin' => $fechaFin->format('Y-m-d')
                ],
                'resumen' => $resumen,
                'reparaciones' => $reparaciones
            ]
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error al generar reporte por técnico',
            'error' => $e->getMessage()
        ], 500);
    }
}

    /**
     * Reporte de inventario (estadísticas)
     * GET /api/reportes/inventario
     */
    public function inventario()
    {
        try {
            $stats = [
                'total_piezas' => Pieza::count(),
                'stock_bajo' => Pieza::where('stock', '<=', 5)->count(),
                'stock_agotado' => Pieza::where('stock', 0)->count(),
                'valor_total' => round(Pieza::sum(DB::raw('precio * stock')), 2),
                'categorias' => DB::table('categoria')->count(),
                'piezas_por_categoria' => DB::table('pieza')
                    ->join('categoria', 'pieza.id_categoria', '=', 'categoria.id_categoria')
                    ->select('categoria.categoria', DB::raw('count(*) as total'))
                    ->groupBy('categoria.categoria')
                    ->get(),
                'piezas_bajo_stock' => Pieza::where('stock', '<=', 5)
                    ->select('id_pieza', 'nombre_pieza', 'stock', 'precio')
                    ->orderBy('stock')
                    ->get()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas de inventario',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Comparativa mensual
     * GET /api/reportes/comparativa-mensual
     */
    public function comparativaMensual(Request $request)
    {
        try {
            $mes = $request->get('mes', Carbon::now()->month);
            $año = $request->get('año', Carbon::now()->year);
            
            $mesAnterior = $mes == 1 ? 12 : $mes - 1;
            $añoAnterior = $mes == 1 ? $año - 1 : $año;

            // Datos del mes actual
            $actual = $this->obtenerDatosMes($mes, $año);
            
            // Datos del mes anterior
            $anterior = $this->obtenerDatosMes($mesAnterior, $añoAnterior);

            return response()->json([
                'success' => true,
                'data' => [
                    'actual' => $actual,
                    'anterior' => $anterior,
                    'comparativa' => [
                        'ingresos' => [
                            'diferencia' => round($actual['ingresos'] - $anterior['ingresos'], 2),
                            'porcentaje' => $this->calcularPorcentaje($anterior['ingresos'], $actual['ingresos'])
                        ],
                        'reparaciones' => [
                            'diferencia' => $actual['reparaciones'] - $anterior['reparaciones'],
                            'porcentaje' => $this->calcularPorcentaje($anterior['reparaciones'], $actual['reparaciones'])
                        ]
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener comparativa',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ============================================
    // PARTE 3: MÉTODOS PRIVADOS (AUXILIARES)
    // ============================================

    /**
     * Obtener datos de un mes específico
     */
    private function obtenerDatosMes($mes, $año)
    {
        $ingresos = ReparacionMultiple::whereMonth('fecha_fin_reparacion', $mes)
            ->whereYear('fecha_fin_reparacion', $año)
            ->where('estado', 'TERMINADO')
            ->sum('precio_total');

        $reparaciones = ReparacionMultiple::whereMonth('fecha_fin_reparacion', $mes)
            ->whereYear('fecha_fin_reparacion', $año)
            ->where('estado', 'TERMINADO')
            ->count();

        $diagnosticos = Diagnostico::whereMonth('fecha_expiracion', $mes)
            ->whereYear('fecha_expiracion', $año)
            ->count();

        return [
            'ingresos' => round($ingresos, 2),
            'reparaciones' => $reparaciones,
            'diagnosticos' => $diagnosticos,
            'mes' => Carbon::createFromDate($año, $mes, 1)->format('F Y')
        ];
    }

    /**
     * Calcular porcentaje de cambio
     */
    private function calcularPorcentaje($anterior, $actual)
    {
        if ($anterior == 0) {
            return $actual > 0 ? 100 : 0;
        }
        return round((($actual - $anterior) / $anterior) * 100, 2);
    }
    /**
 * Reporte de piezas más usadas en un período
 * POST /api/reportes/piezas
 */
public function reportePiezas(Request $request)
{
    try {
        $request->validate([
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
            'categoria_id' => 'nullable|integer|exists:categoria,id_categoria'
        ]);

        $fechaInicio = Carbon::parse($request->fecha_inicio)->startOfDay();
        $fechaFin = Carbon::parse($request->fecha_fin)->endOfDay();

        // Consulta base
        $query = DB::table('reparacion_multiple')
            ->join('pieza', 'reparacion_multiple.id_pieza', '=', 'pieza.id_pieza')
            ->join('categoria', 'pieza.id_categoria', '=', 'categoria.id_categoria')
            ->whereBetween('reparacion_multiple.fecha_fin_reparacion', [$fechaInicio, $fechaFin])
            ->where('reparacion_multiple.estado', 'TERMINADO');

        // Filtrar por categoría si se especifica
        if ($request->has('categoria_id') && $request->categoria_id) {
            $query->where('pieza.id_categoria', $request->categoria_id);
        }

        // 1. TOP PIEZAS MÁS USADAS
        $topPiezas = (clone $query)
            ->select(
                'pieza.id_pieza',
                'pieza.nombre_pieza',
                'pieza.precio as precio_actual',
                'categoria.id_categoria',
                'categoria.categoria',
                DB::raw('COUNT(*) as veces_usada'),
                DB::raw('SUM(reparacion_multiple.precio_total) as total_ingresos'),
                DB::raw('AVG(reparacion_multiple.precio_total) as precio_promedio'),
                DB::raw('MIN(reparacion_multiple.precio_total) as precio_minimo'),
                DB::raw('MAX(reparacion_multiple.precio_total) as precio_maximo')
            )
            ->groupBy(
                'pieza.id_pieza', 
                'pieza.nombre_pieza', 
                'pieza.precio',
                'categoria.id_categoria', 
                'categoria.categoria'
            )
            ->orderByDesc('veces_usada')
            ->limit(20)
            ->get();

        // 2. RESUMEN POR CATEGORÍA
        $resumenCategorias = (clone $query)
            ->select(
                'categoria.id_categoria',
                'categoria.categoria',
                DB::raw('COUNT(*) as total_usos'),
                DB::raw('COUNT(DISTINCT pieza.id_pieza) as piezas_distintas'),
                DB::raw('SUM(reparacion_multiple.precio_total) as total_ingresos'),
                DB::raw('AVG(reparacion_multiple.precio_total) as precio_promedio_categoria')
            )
            ->groupBy('categoria.id_categoria', 'categoria.categoria')
            ->orderByDesc('total_usos')
            ->get();

        // 3. EVOLUCIÓN POR DÍA (para gráfica)
        $evolucionDiaria = (clone $query)
            ->select(
                DB::raw('DATE(reparacion_multiple.fecha_fin_reparacion) as fecha'),
                DB::raw('COUNT(*) as reparaciones'),
                DB::raw('SUM(reparacion_multiple.precio_total) as ingresos'),
                DB::raw('COUNT(DISTINCT pieza.id_pieza) as piezas_distintas')
            )
            ->groupBy(DB::raw('DATE(reparacion_multiple.fecha_fin_reparacion)'))
            ->orderBy('fecha')
            ->get();

        // 4. ESTADÍSTICAS GENERALES
        $totales = [
            'total_usos' => $topPiezas->sum('veces_usada'),
            'total_ingresos' => round($topPiezas->sum('total_ingresos'), 2),
            'piezas_distintas' => $topPiezas->count(),
            'categorias_involucradas' => $resumenCategorias->count(),
            'precio_promedio_general' => $topPiezas->avg('precio_promedio') ? round($topPiezas->avg('precio_promedio'), 2) : 0
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'periodo' => [
                    'inicio' => $fechaInicio->format('Y-m-d'),
                    'fin' => $fechaFin->format('Y-m-d'),
                    'dias' => $fechaInicio->diffInDays($fechaFin) + 1
                ],
                'filtros' => [
                    'categoria_id' => $request->categoria_id ?? null
                ],
                'totales' => $totales,
                'top_piezas' => $topPiezas,
                'por_categoria' => $resumenCategorias,
                'evolucion_diaria' => $evolucionDiaria
            ]
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error al generar reporte de piezas',
            'error' => $e->getMessage()
        ], 500);
    }
}
}