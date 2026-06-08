<?php
// app/Http/Controllers/ReporteGlobalController.php

namespace App\Http\Controllers;

use App\Models\Ingreso_d;
use App\Models\Reparacion;
use App\Models\ReparacionMultiple;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ReporteGlobalController extends Controller
{
    /**
     * Obtener estadísticas globales (totales, porcentajes)
     */
    public function estadisticasGlobales(): JsonResponse
    {
        try {
            // Total de ingresos
            $totalIngresos = Ingreso_d::count();
            
            // Reparaciones completadas (todas las piezas TERMINADO)
            $reparacionesCompletadas = ReparacionMultiple::where('estado', 'TERMINADO')
                ->whereHas('reparacion', function($q) {
                    $q->where('es_garantia', false);
                })
                ->count();
            
            // Reparaciones en proceso (EN_REPARACION, ESPERANDO_PIEZA, APROBADO)
            $reparacionesEnProceso = ReparacionMultiple::whereIn('estado', ['EN_REPARACION', 'ESPERANDO_PIEZA', 'APROBADO'])
                ->whereHas('reparacion', function($q) {
                    $q->where('es_garantia', false);
                })
                ->count();
            
            // Reparaciones pendientes
            $reparacionesPendientes = ReparacionMultiple::where('estado', 'PENDIENTE')
                ->whereHas('reparacion', function($q) {
                    $q->where('es_garantia', false);
                })
                ->count();
            
            // Totales para porcentajes
            $totalReparaciones = $reparacionesCompletadas + $reparacionesEnProceso + $reparacionesPendientes;
            
            $porcentajeExito = $totalReparaciones > 0 
                ? round(($reparacionesCompletadas / $totalReparaciones) * 100, 1)
                : 0;
            
            $porcentajePendiente = $totalReparaciones > 0 
                ? round((($reparacionesEnProceso + $reparacionesPendientes) / $totalReparaciones) * 100, 1)
                : 0;
            
            // Obtener años disponibles para los selectores
            $aniosDisponibles = ReparacionMultiple::select(DB::raw('DISTINCT EXTRACT(YEAR FROM created_at) as anio'))
                ->whereNotNull('created_at')
                ->orderBy('anio', 'desc')
                ->pluck('anio')
                ->toArray();
            
            // Si no hay datos, usar el año actual
            if (empty($aniosDisponibles)) {
                $aniosDisponibles = [date('Y')];
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'total_ingresos' => $totalIngresos,
                    'reparaciones_completadas' => $reparacionesCompletadas,
                    'reparaciones_en_proceso' => $reparacionesEnProceso,
                    'reparaciones_pendientes' => $reparacionesPendientes,
                    'porcentaje_exito' => $porcentajeExito,
                    'porcentaje_pendiente' => $porcentajePendiente,
                    'total_reparaciones' => $totalReparaciones,
                    'anios_disponibles' => $aniosDisponibles,
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error en estadisticasGlobales: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas globales'
            ], 500);
        }
    }
    
    /**
     * Obtener ingresos mensuales
     */
  /**
 * Obtener ingresos mensuales - Versión para PostgreSQL
 */
public function ingresosMensuales(): JsonResponse
{
    try {
        // Usar EXTRACT para PostgreSQL
        $ingresosMensuales = Ingreso_d::whereNotNull('fecha_ingreso')
            ->select(
                DB::raw('EXTRACT(YEAR FROM fecha_ingreso) as año'),
                DB::raw('EXTRACT(MONTH FROM fecha_ingreso) as mes'),
                DB::raw('COUNT(*) as ingresos')
            )
            ->groupBy('año', 'mes')
            ->orderBy('año', 'asc')
            ->orderBy('mes', 'asc')
            ->get()
            ->map(function($item) {
                $nombresMeses = [
                    1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr',
                    5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago',
                    9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic'
                ];
                return [
                    'mes' => $nombresMeses[(int)$item->mes] . ' ' . $item->año,
                    'ingresos' => (int)$item->ingresos,
                    'mes_orden' => $item->año . '-' . str_pad($item->mes, 2, '0', STR_PAD_LEFT)
                ];
            });
        
        return response()->json([
            'success' => true,
            'data' => $ingresosMensuales
        ]);
        
    } catch (\Exception $e) {
        Log::error('Error en ingresosMensuales: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Error al obtener ingresos mensuales: ' . $e->getMessage()
        ], 500);
    }
}
    /**
     * Comparar dos meses específicos
     */
    public function compararMeses(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'mes1' => 'required|integer|between:1,12',
                'anio1' => 'required|integer|min:2000',
                'mes2' => 'required|integer|between:1,12',
                'anio2' => 'required|integer|min:2000',
            ]);
            
            $datosMes1 = $this->obtenerDatosPorMes($request->mes1, $request->anio1);
            $datosMes2 = $this->obtenerDatosPorMes($request->mes2, $request->anio2);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'datos_mes1' => $datosMes1,
                    'datos_mes2' => $datosMes2,
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error en compararMeses: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al comparar meses'
            ], 500);
        }
    }
    /**
 * Obtener reparaciones completadas por mes
 */
public function reparacionesMensuales(): JsonResponse
{
    try {
        // PostgreSQL
        $reparaciones = ReparacionMultiple::where('estado', 'TERMINADO')
            ->whereHas('reparacion', function($q) {
                $q->where('es_garantia', false);
            })
            ->select(
                DB::raw("TO_CHAR(created_at, 'YYYY-MM') as mes_orden"),
                DB::raw("TO_CHAR(created_at, 'Mon YYYY') as mes"),
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('mes_orden', 'mes')
            ->orderBy('mes_orden', 'asc')
            ->get()
            ->map(function($item) {
                return [
                    'mes' => ucfirst($item->mes),
                    'total' => (int)$item->total,
                    'mes_orden' => $item->mes_orden
                ];
            });
        
        return response()->json([
            'success' => true,
            'data' => $reparaciones
        ]);
        
    } catch (\Exception $e) {
        Log::error('Error en reparacionesMensuales: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Error al obtener reparaciones mensuales'
        ], 500);
    }
}
    
    /**
 * Obtener top marcas reparadas por mes
 */
    /**
 * Obtener top marcas reparadas por mes
 */
/**
 * Obtener top marcas reparadas por mes
 */
public function topMarcasMensual(Request $request)
{
    try {
        $anio = $request->input('anio', date('Y'));
        $limit = $request->input('limit', 5);

        $topMarcasNames = DB::table('reparacion_multiple')
            ->join('reparacion', 'reparacion_multiple.id_reparacion', '=', 'reparacion.id_reparacion')
            ->join('diagnostico', 'reparacion.id_diagnostico', '=', 'diagnostico.id_diagnostico')
            ->join('ingreso_d', 'diagnostico.id_ingreso', '=', 'ingreso_d.id_ingreso')
            ->join('dispositivo', 'ingreso_d.id_dispositivo', '=', 'dispositivo.id_dispositivo')
            ->join('modelo', 'dispositivo.id_modelo', '=', 'modelo.id_modelo')   // ✅
            ->join('marca', 'modelo.id_marca', '=', 'marca.id_marca')            // ✅
            ->whereYear('reparacion_multiple.created_at', $anio)
            ->where('reparacion.es_garantia', false)
            ->select('marca.marca as marca', DB::raw('COUNT(*) as total'))
            ->groupBy('marca.marca')
            ->orderBy('total', 'DESC')
            ->limit($limit)
            ->pluck('marca')
            ->toArray();

        if (empty($topMarcasNames)) {
            return response()->json([
                'success' => true,
                'data' => [
                    'anio' => $anio,
                    'meses' => ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'],
                    'marcas' => []
                ]
            ]);
        }

        $resultados = [];
        $mesesNombres = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

        foreach ($topMarcasNames as $marcaNombre) {
            $mensual = array_fill(0, 12, 0);

            $datos = DB::table('reparacion_multiple')
                ->join('reparacion', 'reparacion_multiple.id_reparacion', '=', 'reparacion.id_reparacion')
                ->join('diagnostico', 'reparacion.id_diagnostico', '=', 'diagnostico.id_diagnostico')
                ->join('ingreso_d', 'diagnostico.id_ingreso', '=', 'ingreso_d.id_ingreso')
                ->join('dispositivo', 'ingreso_d.id_dispositivo', '=', 'dispositivo.id_dispositivo')
                ->join('modelo', 'dispositivo.id_modelo', '=', 'modelo.id_modelo')   // ✅
                ->join('marca', 'modelo.id_marca', '=', 'marca.id_marca')            // ✅
                ->whereYear('reparacion_multiple.created_at', $anio)
                ->where('reparacion.es_garantia', false)
                ->where('marca.marca', $marcaNombre)
                ->select(
                    DB::raw('EXTRACT(MONTH FROM reparacion_multiple.created_at) as mes'),
                    DB::raw('COUNT(*) as total')
                )
                ->groupBy('mes')
                ->orderBy('mes')
                ->get();

            foreach ($datos as $dato) {
                $mensual[$dato->mes - 1] = $dato->total;
            }

            $resultados[] = [
                'marca' => $marcaNombre,
                'data' => $mensual
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'anio' => $anio,
                'meses' => $mesesNombres,
                'marcas' => $resultados
            ]
        ]);

    } catch (\Exception $e) {
        \Log::error('Error en topMarcasMensual: ' . $e->getMessage());

        return response()->json([
            'success' => false,
            'message' => 'Error al obtener top marcas: ' . $e->getMessage()
        ], 500);
    }
}
    public function compararAnios(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'anio1' => 'required|integer|min:2000',
                'anio2' => 'required|integer|min:2000',
            ]);
            
            $datosAnio1 = $this->obtenerDatosPorAnio($request->anio1);
            $datosAnio2 = $this->obtenerDatosPorAnio($request->anio2);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'datos_anio1' => $datosAnio1,
                    'datos_anio2' => $datosAnio2,
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error en compararAnios: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al comparar años'
            ], 500);
        }
    }
    
    /**
     * Obtener datos de reparaciones por mes (día a día)
     */
    private function obtenerDatosPorMes($mes, $anio): array
    {
        $nombreMes = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        ][$mes];
        
        // Obtener el último día del mes
        $ultimoDia = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);
        
        // Preparar array de días
        $dias = [];
        $cantidades = [];
        
        for ($dia = 1; $dia <= $ultimoDia; $dia++) {
            $dias[] = $dia;
            $cantidades[] = 0;
        }
        
        // Consultar reparaciones múltiples creadas en ese mes
        $reparaciones = ReparacionMultiple::whereYear('created_at', $anio)
            ->whereMonth('created_at', $mes)
            ->whereHas('reparacion', function($q) {
                $q->where('es_garantia', false);
            })
            ->select(DB::raw('EXTRACT(DAY FROM created_at) as dia'), DB::raw('COUNT(*) as total'))
            ->groupBy('dia')
            ->get();
        
        foreach ($reparaciones as $rep) {
            $dia = (int)$rep->dia;
            if ($dia >= 1 && $dia <= $ultimoDia) {
                $cantidades[$dia - 1] = $rep->total;
            }
        }
        
        $total = array_sum($cantidades);
        
        return [
            'nombre' => "{$nombreMes} {$anio}",
            'mes' => $mes,
            'anio' => $anio,
            'total' => $total,
            'dias' => $dias,
            'cantidades' => $cantidades,
        ];
    }
    
   
  /**
 * Obtener datos de reparaciones por año (incluyendo terminadas, canceladas, ingresos)
 */
private function obtenerDatosPorAnio($anio): array
{
    $meses = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12];
    $nombresMeses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    
    // Totales por mes (reparaciones totales)
    $cantidades = array_fill(0, 12, 0);
    
    // NUEVOS: Terminadas por mes
    $terminadasPorMes = array_fill(0, 12, 0);
    
    // NUEVOS: Canceladas por mes
    $canceladasPorMes = array_fill(0, 12, 0);
    
    // NUEVOS: Ingresos por mes
    $ingresosPorMes = array_fill(0, 12, 0);
    
    // Consultar reparaciones múltiples por mes (TOTALES)
    $reparaciones = ReparacionMultiple::whereYear('created_at', $anio)
        ->whereHas('reparacion', function($q) {
            $q->where('es_garantia', false);
        })
        ->select(DB::raw('EXTRACT(MONTH FROM created_at) as mes'), DB::raw('COUNT(*) as total'))
        ->groupBy('mes')
        ->get();
    
    foreach ($reparaciones as $rep) {
        $mes = (int)$rep->mes;
        if ($mes >= 1 && $mes <= 12) {
            $cantidades[$mes - 1] = $rep->total;
        }
    }
    
    // NUEVO: Consultar reparaciones TERMINADAS por mes
    $terminadas = ReparacionMultiple::whereYear('created_at', $anio)
        ->where('estado', 'TERMINADO')
        ->whereHas('reparacion', function($q) {
            $q->where('es_garantia', false);
        })
        ->select(DB::raw('EXTRACT(MONTH FROM created_at) as mes'), DB::raw('COUNT(*) as total'))
        ->groupBy('mes')
        ->get();
    
    foreach ($terminadas as $term) {
        $mes = (int)$term->mes;
        if ($mes >= 1 && $mes <= 12) {
            $terminadasPorMes[$mes - 1] = $term->total;
        }
    }
    
    // NUEVO: Consultar reparaciones CANCELADAS o RECHAZADAS por mes
    $canceladas = ReparacionMultiple::whereYear('created_at', $anio)
        ->whereIn('estado', ['CANCELADO', 'RECHAZADO'])
        ->whereHas('reparacion', function($q) {
            $q->where('es_garantia', false);
        })
        ->select(DB::raw('EXTRACT(MONTH FROM created_at) as mes'), DB::raw('COUNT(*) as total'))
        ->groupBy('mes')
        ->get();
    
    foreach ($canceladas as $can) {
        $mes = (int)$can->mes;
        if ($mes >= 1 && $mes <= 12) {
            $canceladasPorMes[$mes - 1] = $can->total;
        }
    }
    
    // NUEVO: Consultar INGRESOS por mes (usando fecha_ingreso)
    $ingresos = Ingreso_d::whereYear('fecha_ingreso', $anio)
        ->select(DB::raw('EXTRACT(MONTH FROM fecha_ingreso) as mes'), DB::raw('COUNT(*) as total'))
        ->groupBy('mes')
        ->get();
    
    foreach ($ingresos as $ing) {
        $mes = (int)$ing->mes;
        if ($mes >= 1 && $mes <= 12) {
            $ingresosPorMes[$mes - 1] = $ing->total;
        }
    }
    
    // Totales del año
    $total = array_sum($cantidades);
    $totalTerminadas = array_sum($terminadasPorMes);
    $totalCanceladas = array_sum($canceladasPorMes);
    $totalIngresos = array_sum($ingresosPorMes);
    
    return [
        'nombre' => (string)$anio,
        'anio' => $anio,
        'total' => $total,
        'total_terminadas' => $totalTerminadas,
        'total_canceladas' => $totalCanceladas,
        'total_ingresos' => $totalIngresos,
        'meses' => $meses,
        'nombres_meses' => $nombresMeses,
        'cantidades' => $cantidades,           // Totales de reparaciones (existente)
        'terminadas_por_mes' => $terminadasPorMes,  // NUEVO
        'canceladas_por_mes' => $canceladasPorMes,  // NUEVO
        'ingresos_por_mes' => $ingresosPorMes,      // NUEVO
    ];
}
public function compararMesesResumen(Request $request): JsonResponse
{
    try {
        $request->validate([
            'mes1' => 'required|integer|between:1,12',
            'anio1' => 'required|integer|min:2000',
            'mes2' => 'required|integer|between:1,12',
            'anio2' => 'required|integer|min:2000',
        ]);
 
        $datosMes1 = $this->obtenerResumenMes($request->mes1, $request->anio1);
        $datosMes2 = $this->obtenerResumenMes($request->mes2, $request->anio2);
 
        return response()->json([
            'success' => true,
            'data' => [
                'mes1' => $datosMes1,
                'mes2' => $datosMes2,
            ]
        ]);
 
    } catch (\Exception $e) {
        Log::error('Error en compararMesesResumen: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Error al comparar resumen de meses'
        ], 500);
    }
}
 
/**
 * Obtener resumen de un mes: terminadas, canceladas/rechazadas, ingresos
 */
private function obtenerResumenMes($mes, $anio): array
{
    $nombreMes = [
        1 => 'Enero',    2 => 'Febrero',   3 => 'Marzo',
        4 => 'Abril',    5 => 'Mayo',       6 => 'Junio',
        7 => 'Julio',    8 => 'Agosto',     9 => 'Septiembre',
        10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
    ][$mes];
 
    // Reparaciones terminadas en el mes
    $terminadas = ReparacionMultiple::whereYear('created_at', $anio)
        ->whereMonth('created_at', $mes)
        ->where('estado', 'TERMINADO')
        ->whereHas('reparacion', function ($q) {
            $q->where('es_garantia', false);
        })
        ->count();
 
    // Reparaciones canceladas o rechazadas en el mes
    $canceladas = ReparacionMultiple::whereYear('created_at', $anio)
        ->whereMonth('created_at', $mes)
        ->whereIn('estado', ['CANCELADO', 'RECHAZADO'])
        ->whereHas('reparacion', function ($q) {
            $q->where('es_garantia', false);
        })
        ->count();
 
    // Ingresos del mes (fecha_ingreso = CREATED_AT del modelo Ingreso_d)
    $ingresos = Ingreso_d::whereYear('fecha_ingreso', $anio)
        ->whereMonth('fecha_ingreso', $mes)
        ->count();
 
    return [
        'nombre'     => "{$nombreMes} {$anio}",
        'mes'        => $mes,
        'anio'       => $anio,
        'terminadas' => $terminadas,
        'canceladas' => $canceladas,
        'ingresos'   => $ingresos,
    ];
}
 
}