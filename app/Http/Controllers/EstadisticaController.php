<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Pieza;
use App\Models\ReparacionMultiple;
use App\Models\Reparacion;
use App\Models\Usuario;
use App\Models\Categoria;
use Carbon\Carbon;

class EstadisticaController extends Controller
{
    // ============================================
    // ENDPOINTS PÚBLICOS
    // ============================================

    /**
     * Dashboard principal (todas las estadísticas juntas)
     * GET /api/estadisticas/dashboard
     */
   public function dashboard()
{
    try {
        $resumen = $this->obtenerResumen();
        
        $data = [
            'resumen' => [
                'total_piezas' => $resumen['total_piezas'],
                'total_tecnicos' => $resumen['total_tecnicos'],
                'total_recepcionistas' => $resumen['total_recepcionistas'],
                'reparaciones_hoy' => $resumen['reparaciones_hoy'],
                'stock_bajo' => $resumen['stock_bajo'],
            ],
            'ingresos' => $this->obtenerIngresosMes(),
            'reparaciones_por_estado' => $this->obtenerReparacionesPorEstado(),
            'top_tecnicos' => $this->obtenerTopTecnicos(),
              'top_recepcionistas' => $this->obtenerTopRecepcionistas(), 
            'piezas_mas_usadas' => $this->obtenerPiezasMasUsadas(),
            'tiempos_por_categoria' => $this->obtenerTiemposPorCategoria(),
            'periodo' => [
                'mes_actual' => Carbon::now()->format('F Y'),
                'mes_anterior' => Carbon::now()->subMonth()->format('F Y')
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $data
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error al obtener estadísticas',
            'error' => $e->getMessage()
        ], 500);
    }
}

    /**
     * Estadísticas de inventario
     * GET /api/estadisticas/inventario
     */
    public function inventario()
    {
        try {
            $data = [
                'total_piezas' => Pieza::count(),
                'valor_total' => round(Pieza::sum(DB::raw('precio * stock')), 2),
                'stock_bajo' => Pieza::where('stock', '<=', 5)->count(),
                'stock_agotado' => Pieza::where('stock', 0)->count(),
                'piezas_por_categoria' => DB::table('pieza')
                    ->join('categoria', 'pieza.id_categoria', '=', 'categoria.id_categoria')
                    ->select('categoria.categoria', DB::raw('count(*) as total'))
                    ->groupBy('categoria.categoria')
                    ->get(),
                'precio_promedio' => round(Pieza::avg('precio'), 2)
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas de inventario'
            ], 500);
        }
    }

    /**
     * Top técnicos del mes
     * GET /api/estadisticas/top-tecnicos
     */
    public function topTecnicos(Request $request)
    {
        try {
            $limite = $request->get('limite', 5);
            $mes = $request->get('mes', Carbon::now()->month);
            $año = $request->get('año', Carbon::now()->year);

            $topTecnicos = DB::table('reparacion_multiple')
                ->join('reparacion', 'reparacion_multiple.id_reparacion', '=', 'reparacion.id_reparacion')
                ->join('usuario', 'reparacion.id_usuario', '=', 'usuario.id_usuario')
                ->whereMonth('reparacion_multiple.fecha_fin_reparacion', $mes)
                ->whereYear('reparacion_multiple.fecha_fin_reparacion', $año)
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
                ->limit($limite)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $topTecnicos,
                'periodo' => [
                    'mes' => Carbon::createFromDate($año, $mes, 1)->format('F Y'),
                    'limite' => $limite
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener top técnicos'
            ], 500);
        }
    }

    /**
     * Piezas más usadas
     * GET /api/estadisticas/piezas-mas-usadas
     */
    public function piezasMasUsadas(Request $request)
    {
        try {
            $limite = $request->get('limite', 5);
            $mes = $request->get('mes', Carbon::now()->month);
            $año = $request->get('año', Carbon::now()->year);

            $piezas = DB::table('reparacion_multiple')
                ->join('pieza', 'reparacion_multiple.id_pieza', '=', 'pieza.id_pieza')
                ->join('categoria', 'pieza.id_categoria', '=', 'categoria.id_categoria')
                ->whereMonth('reparacion_multiple.fecha_fin_reparacion', $mes)
                ->whereYear('reparacion_multiple.fecha_fin_reparacion', $año)
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
                ->limit($limite)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $piezas
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener piezas más usadas'
            ], 500);
        }
    }

    /**
     * Ingresos mensuales (para gráfica)
     * GET /api/estadisticas/ingresos-mensuales
     */
    public function ingresosMensuales(Request $request)
    {
        try {
            $año = $request->get('año', Carbon::now()->year);
            $meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
            $ingresos = [];

            for ($i = 1; $i <= 12; $i++) {
                $total = ReparacionMultiple::whereMonth('fecha_fin_reparacion', $i)
                    ->whereYear('fecha_fin_reparacion', $año)
                    ->where('estado', 'TERMINADO')
                    ->sum('precio_total');

                $ingresos[] = [
                    'mes' => $meses[$i - 1],
                    'ingresos' => round($total, 2)
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'año' => $año,
                    'ingresos_por_mes' => $ingresos,
                    'total_anual' => round(array_sum(array_column($ingresos, 'ingresos')), 2)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener ingresos mensuales'
            ], 500);
        }
    }

    /**
     * Reparaciones por estado
     * GET /api/estadisticas/reparaciones-por-estado
     */
    public function reparacionesPorEstado()
    {
        try {
            $estados = ReparacionMultiple::select('estado', DB::raw('count(*) as total'))
                ->groupBy('estado')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $estados
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener reparaciones por estado'
            ], 500);
        }
    }

    /**
     * Tiempo promedio por categoría
     * GET /api/estadisticas/tiempos-por-categoria
     */
    public function tiemposPorCategoria()
    {
        try {
            $tiempos = DB::table('reparacion_multiple')
                ->join('pieza', 'reparacion_multiple.id_pieza', '=', 'pieza.id_pieza')
                ->join('categoria', 'pieza.id_categoria', '=', 'categoria.id_categoria')
                ->where('reparacion_multiple.estado', 'TERMINADO')
                ->whereNotNull('fecha_ini_reparacion')
                ->whereNotNull('fecha_fin_reparacion')
                ->select(
                    'categoria.categoria',
                    DB::raw('AVG(EXTRACT(EPOCH FROM (fecha_fin_reparacion - fecha_ini_reparacion)) / 3600) as horas_promedio'),
                    DB::raw('COUNT(*) as total_reparaciones')
                )
                ->groupBy('categoria.categoria')
                ->orderByDesc('horas_promedio')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $tiempos
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener tiempos por categoría'
            ], 500);
        }
    }

    // ============================================
    // MÉTODOS PRIVADOS AUXILIARES
    // ============================================

    /**
     * Obtener resumen general
     */
    private function obtenerResumen()
    {
        $hoy = Carbon::today();

        return [
            'total_piezas' => Pieza::count(),
            'total_tecnicos' => Usuario::where('es_tecnico', true)->count(),
            'total_recepcionistas' => Usuario::where('es_recepcionista', true)->count(),
            'reparaciones_hoy' => ReparacionMultiple::whereDate('fecha_fin_reparacion', $hoy)
                ->where('estado', 'TERMINADO')
                ->count(),
            'stock_bajo' => Pieza::where('stock', '<=', 5)->count(),
        ];
    }

    /**
     * Obtener ingresos del mes actual
     */
    private function obtenerIngresosMes()
    {
        return [
            'mes_actual' => round(ReparacionMultiple::whereMonth('fecha_fin_reparacion', Carbon::now()->month)
                ->whereYear('fecha_fin_reparacion', Carbon::now()->year)
                ->where('estado', 'TERMINADO')
                ->sum('precio_total'), 2),
            'mes_anterior' => round(ReparacionMultiple::whereMonth('fecha_fin_reparacion', Carbon::now()->subMonth()->month)
                ->whereYear('fecha_fin_reparacion', Carbon::now()->subMonth()->year)
                ->where('estado', 'TERMINADO')
                ->sum('precio_total'), 2)
        ];
    }

    /**
     * Obtener top técnicos del mes
     */
    private function obtenerTopTecnicos()
    {
        return DB::table('reparacion_multiple')
            ->join('reparacion', 'reparacion_multiple.id_reparacion', '=', 'reparacion.id_reparacion')
            ->join('usuario', 'reparacion.id_usuario', '=', 'usuario.id_usuario')
            ->whereMonth('reparacion_multiple.fecha_fin_reparacion', Carbon::now()->month)
            ->whereYear('reparacion_multiple.fecha_fin_reparacion', Carbon::now()->year)
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
    }

    /**
     * Obtener piezas más usadas del mes
     */
    private function obtenerPiezasMasUsadas()
    {
        return DB::table('reparacion_multiple')
            ->join('pieza', 'reparacion_multiple.id_pieza', '=', 'pieza.id_pieza')
            ->join('categoria', 'pieza.id_categoria', '=', 'categoria.id_categoria')
            ->whereMonth('reparacion_multiple.fecha_fin_reparacion', Carbon::now()->month)
            ->whereYear('reparacion_multiple.fecha_fin_reparacion', Carbon::now()->year)
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
            ->limit(5)
            ->get();
    }
    /**
 * Top recepcionistas del mes
 * GET /api/estadisticas/top-recepcionistas
 */
    public function topRecepcionistas(Request $request)
    {
        try {
            $limite = $request->get('limite', 5);
            $mes = $request->get('mes', Carbon::now()->month);
            $año = $request->get('año', Carbon::now()->year);

            $topRecepcionistas = DB::table('ingreso_d')
                ->join('usuario', 'ingreso_d.id_usuario', '=', 'usuario.id_usuario')
                ->whereMonth('ingreso_d.fecha_ingreso', $mes)
                ->whereYear('ingreso_d.fecha_ingreso', $año)
                ->where('usuario.es_recepcionista', true)
                ->select(
                    'usuario.id_usuario',
                    'usuario.nombre',
                    'usuario.apellido',
                    DB::raw('COUNT(*) as total_ingresos'),
                    DB::raw('COUNT(DISTINCT ingreso_d.id_dispositivo) as dispositivos_atendidos')
                )
                ->groupBy('usuario.id_usuario', 'usuario.nombre', 'usuario.apellido')
                ->orderByDesc('total_ingresos')
                ->limit($limite)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $topRecepcionistas,
                'periodo' => [
                    'mes' => Carbon::createFromDate($año, $mes, 1)->format('F Y'),
                    'limite' => $limite
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener top recepcionistas'
            ], 500);
        }
    }
    public function topMarcas(Request $request)
{
    try {
        $limite = $request->get('limite', 5);
        $mes = $request->get('mes', Carbon::now()->month);
        $año = $request->get('año', Carbon::now()->year);

        $topMarcas = DB::table('reparacion_multiple')
            ->join('reparacion', 'reparacion_multiple.id_reparacion', '=', 'reparacion.id_reparacion')
            ->join('ingreso_d', 'reparacion.id_ingreso', '=', 'ingreso_d.id_ingreso')
            ->join('dispositivo', 'ingreso_d.id_dispositivo', '=', 'dispositivo.id_dispositivo')
            ->join('modelo', 'dispositivo.id_modelo', '=', 'modelo.id_modelo')
            ->join('marca', 'modelo.id_marca', '=', 'marca.id_marca')
            ->whereMonth('reparacion_multiple.fecha_fin_reparacion', $mes)
            ->whereYear('reparacion_multiple.fecha_fin_reparacion', $año)
            ->where('reparacion_multiple.estado', 'TERMINADO')
            ->select(
                'marca.id_marca',
                'marca.marca',
                DB::raw('COUNT(*) as total_reparaciones'),
                DB::raw('SUM(reparacion_multiple.precio_total) as total_ingresos')
            )
            ->groupBy('marca.id_marca', 'marca.marca')
            ->orderByDesc('total_reparaciones')
            ->limit($limite)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $topMarcas,
            'periodo' => [
                'mes' => Carbon::createFromDate($año, $mes, 1)->format('F Y'),
                'limite' => $limite
            ]
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error al obtener top marcas'
        ], 500);
    }
}

    /**
     * Obtener reparaciones por estado
     */
    private function obtenerReparacionesPorEstado()
    {
        return ReparacionMultiple::select('estado', DB::raw('count(*) as total'))
            ->groupBy('estado')
            ->get()
            ->pluck('total', 'estado')
            ->toArray();
    }

    /**
     * Obtener tiempos por categoría
     */
    private function obtenerTiemposPorCategoria()
    {
        return DB::table('reparacion_multiple')
            ->join('pieza', 'reparacion_multiple.id_pieza', '=', 'pieza.id_pieza')
            ->join('categoria', 'pieza.id_categoria', '=', 'categoria.id_categoria')
            ->where('reparacion_multiple.estado', 'TERMINADO')
            ->whereNotNull('fecha_ini_reparacion')
            ->whereNotNull('fecha_fin_reparacion')
            ->select(
                'categoria.categoria',
                DB::raw('AVG(EXTRACT(EPOCH FROM (fecha_fin_reparacion - fecha_ini_reparacion)) / 3600) as horas_promedio')
            )
            ->groupBy('categoria.categoria')
            ->orderByDesc('horas_promedio')
            ->get();
    }
    /**
 * Obtener top recepcionistas del mes (privado)
 */
private function obtenerTopRecepcionistas()
{
    return DB::table('ingreso_d')
        ->join('usuario', 'ingreso_d.id_usuario', '=', 'usuario.id_usuario')
        ->whereMonth('ingreso_d.fecha_ingreso', Carbon::now()->month)
        ->whereYear('ingreso_d.fecha_ingreso', Carbon::now()->year)
        ->where('usuario.es_recepcionista', true)
        ->select(
            'usuario.id_usuario',
            'usuario.nombre',
            'usuario.apellido',
            DB::raw('COUNT(*) as total_ingresos'),
            DB::raw('COUNT(DISTINCT ingreso_d.id_dispositivo) as dispositivos_atendidos')
        )
        ->groupBy('usuario.id_usuario', 'usuario.nombre', 'usuario.apellido')
        ->orderByDesc('total_ingresos')
        ->limit(5)
        ->get();
}

}