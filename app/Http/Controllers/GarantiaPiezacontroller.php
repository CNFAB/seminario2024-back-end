<?php

namespace App\Http\Controllers;

use App\Models\GarantiaPieza;
use App\Http\Requests\GarantiaPieza\StoreGarantiaPiezaRequest;
use App\Http\Requests\GarantiaPieza\UpdateGarantiaPiezaRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GarantiaPiezaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        try {
            $garantiasPiezas = GarantiaPieza::with([
                    'garantia',
                    'pieza',
                    'categoria',
                    'reparacionMultiple'
                ])
                ->orderBy('created_at', 'desc')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $garantiasPiezas,
                'message' => 'Garantías por pieza obtenidas exitosamente',
                'count' => $garantiasPiezas->count()
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener garantías por pieza',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreGarantiaPiezaRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            
            $data = $request->validated();
            
            // Verificar que la fecha_fin no sea menor a la fecha actual
            if (isset($data['fecha_fin']) && $data['fecha_fin'] < now()->format('Y-m-d')) {
                $data['estado'] = 'VENCIDA';
            }
            
            $garantiaPieza = GarantiaPieza::create($data);
            
            // Actualizar la garantía principal (fecha_fin más larga)
            $this->actualizarGarantiaPrincipal($garantiaPieza->id_garantia);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'data' => $garantiaPieza->load(['pieza', 'categoria', 'garantia']),
                'message' => 'Garantía por pieza creada exitosamente'
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la garantía por pieza',
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
            $garantiaPieza = GarantiaPieza::with([
                    'garantia',
                    'garantia.reparacion.dispositivo.cliente',
                    'pieza',
                    'categoria',
                    'reparacionMultiple',
                    'reclamos'
                ])->find($id);
            
            if (!$garantiaPieza) {
                return response()->json([
                    'success' => false,
                    'message' => 'Garantía por pieza no encontrada'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $garantiaPieza,
                'message' => 'Garantía por pieza obtenida exitosamente'
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener la garantía por pieza',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateGarantiaPiezaRequest $request, int $id): JsonResponse
    {
        try {
            DB::beginTransaction();
            
            $garantiaPieza = GarantiaPieza::find($id);
            
            if (!$garantiaPieza) {
                return response()->json([
                    'success' => false,
                    'message' => 'Garantía por pieza no encontrada'
                ], 404);
            }
            
            $data = $request->validated();
            
            // Verificar si la fecha_fin es menor a la fecha actual para cambiar estado
            if (isset($data['fecha_fin']) && $data['fecha_fin'] < now()->format('Y-m-d')) {
                $data['estado'] = 'VENCIDA';
            } elseif (!isset($data['fecha_fin']) && $garantiaPieza->fecha_fin < now()) {
                $data['estado'] = 'VENCIDA';
            }
            
            $garantiaPieza->update($data);
            
            // Actualizar la garantía principal (fecha_fin más larga)
            $this->actualizarGarantiaPrincipal($garantiaPieza->id_garantia);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'data' => $garantiaPieza->fresh(['pieza', 'categoria']),
                'message' => 'Garantía por pieza actualizada exitosamente'
            ], 200);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la garantía por pieza',
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
            
            $garantiaPieza = GarantiaPieza::find($id);
            
            if (!$garantiaPieza) {
                return response()->json([
                    'success' => false,
                    'message' => 'Garantía por pieza no encontrada'
                ], 404);
            }
            
            $idGarantia = $garantiaPieza->id_garantia;
            $garantiaPieza->delete();
            
            // Actualizar la garantía principal (recalcular fecha_fin más larga)
            $this->actualizarGarantiaPrincipal($idGarantia);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Garantía por pieza eliminada exitosamente'
            ], 200);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la garantía por pieza',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ==========================================
    // MÉTODOS ADICIONALES
    // ==========================================

    /**
     * Actualizar la garantía principal con la fecha_fin más larga
     */
    private function actualizarGarantiaPrincipal(int $idGarantia): void
    {
        $garantia = \App\Models\Garantia::find($idGarantia);
        
        if ($garantia) {
            $fechaFinMax = GarantiaPieza::where('id_garantia', $idGarantia)
                ->where('estado', 'ACTIVA')
                ->max('fecha_fin');
            
            if ($fechaFinMax) {
                $garantia->fecha_fin = $fechaFinMax;
                $garantia->duracion_meses = \Carbon\Carbon::parse($garantia->fecha_inicio)->diffInMonths($fechaFinMax);
                $garantia->save();
            }
        }
    }

    /**
     * Obtener garantías por pieza activas
     */
    public function activas(): JsonResponse
    {
        try {
            $garantiasPiezas = GarantiaPieza::activas()
                ->with(['pieza', 'categoria', 'garantia.reparacion.dispositivo.cliente'])
                ->orderBy('fecha_fin', 'asc')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $garantiasPiezas,
                'message' => 'Garantías por pieza activas obtenidas exitosamente',
                'count' => $garantiasPiezas->count()
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener garantías por pieza activas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener garantías por pieza vencidas
     */
    public function vencidas(): JsonResponse
    {
        try {
            $garantiasPiezas = GarantiaPieza::vencidas()
                ->with(['pieza', 'categoria', 'garantia'])
                ->orderBy('fecha_fin', 'desc')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $garantiasPiezas,
                'message' => 'Garantías por pieza vencidas obtenidas exitosamente',
                'count' => $garantiasPiezas->count()
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener garantías por pieza vencidas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener garantías por pieza por garantía
     */
    public function porGarantia(int $idGarantia): JsonResponse
    {
        try {
            $garantiasPiezas = GarantiaPieza::porGarantia($idGarantia)
                ->with(['pieza.categoria', 'categoria'])
                ->orderBy('fecha_fin', 'asc')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $garantiasPiezas,
                'message' => 'Garantías por pieza de la garantía obtenidas exitosamente',
                'count' => $garantiasPiezas->count()
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener garantías por pieza de la garantía',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener garantías por pieza por categoría
     */
    public function porCategoria(int $idCategoria): JsonResponse
    {
        try {
            $garantiasPiezas = GarantiaPieza::porCategoria($idCategoria)
                ->with(['pieza', 'garantia'])
                ->orderBy('created_at', 'desc')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $garantiasPiezas,
                'message' => 'Garantías por pieza de la categoría obtenidas exitosamente',
                'count' => $garantiasPiezas->count()
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener garantías por pieza de la categoría',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener garantías por pieza por pieza
     */
    public function porPieza(int $idPieza): JsonResponse
    {
        try {
            $garantiasPiezas = GarantiaPieza::where('id_pieza', $idPieza)
                ->with(['garantia', 'categoria'])
                ->orderBy('created_at', 'desc')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $garantiasPiezas,
                'message' => 'Garantías por pieza de la pieza obtenidas exitosamente',
                'count' => $garantiasPiezas->count()
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener garantías por pieza de la pieza',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verificar si una pieza específica está en garantía
     */
    public function verificarPieza(int $idPieza, int $idReparacionMultiple = null): JsonResponse
    {
        try {
            $query = GarantiaPieza::where('id_pieza', $idPieza)
                ->activas()
                ->with(['garantia', 'pieza', 'categoria']);
            
            if ($idReparacionMultiple) {
                $query->where('id_reparacion_multiple', $idReparacionMultiple);
            }
            
            $garantiaPieza = $query->first();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'tiene_garantia_activa' => !is_null($garantiaPieza),
                    'garantia_pieza' => $garantiaPieza,
                    'dias_restantes' => $garantiaPieza ? $garantiaPieza->dias_restantes : 0,
                    'meses_restantes' => $garantiaPieza ? $garantiaPieza->meses_restantes : 0
                ],
                'message' => $garantiaPieza ? 'La pieza está en garantía' : 'La pieza no está en garantía'
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al verificar garantía de la pieza',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener garantías que vencen pronto (próximos N días)
     */
    public function porVencer(Request $request): JsonResponse
    {
        try {
            $dias = $request->input('dias', 30); // Por defecto 30 días
            
            $garantiasPiezas = GarantiaPieza::activas()
                ->where('fecha_fin', '<=', now()->addDays($dias))
                ->where('fecha_fin', '>=', now())
                ->with(['pieza', 'categoria', 'garantia.reparacion.dispositivo.cliente'])
                ->orderBy('fecha_fin', 'asc')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $garantiasPiezas,
                'message' => "Garantías que vencen en los próximos {$dias} días",
                'count' => $garantiasPiezas->count()
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener garantías por vencer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Resumen de garantías por pieza
     */
    public function resumen(): JsonResponse
    {
        try {
            $total = GarantiaPieza::count();
            $activas = GarantiaPieza::activas()->count();
            $vencidas = GarantiaPieza::vencidas()->count();
            $reclamadas = GarantiaPieza::where('estado', 'RECLAMADA')->count();
            
            // Por categoría
            $porCategoria = GarantiaPieza::select('id_categoria', DB::raw('count(*) as total'))
                ->whereNotNull('id_categoria')
                ->groupBy('id_categoria')
                ->with('categoria')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'total' => $total,
                    'activas' => $activas,
                    'vencidas' => $vencidas,
                    'reclamadas' => $reclamadas,
                    'por_categoria' => $porCategoria,
                    'porcentaje_activas' => $total > 0 ? round(($activas / $total) * 100, 2) : 0
                ],
                'message' => 'Resumen de garantías por pieza obtenido exitosamente'
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener resumen de garantías por pieza',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}