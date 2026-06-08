<?php

namespace App\Http\Controllers;

use App\Models\Presupuesto;
use App\Models\PresupuestoDetalle;
use App\Http\Requests\PresupuestoDetalle\StoreDetalleRequest;
use App\Http\Requests\PresupuestoDetalle\UpdateDetalleRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PresupuestoDetalleController extends Controller
{
    /**
     * Listar todos los detalles con filtros
     * GET /api/presupuestos-detalles
     */
    public function index(Request $request)
    {
        try {
            $query = PresupuestoDetalle::with(['presupuesto', 'pieza']);
            
            // Filtrar por presupuesto
            if ($request->has('id_presupuesto') && $request->id_presupuesto) {
                $query->where('id_presupuesto', $request->id_presupuesto);
            }
            
            // Filtrar por pieza
            if ($request->has('id_pieza') && $request->id_pieza) {
                $query->where('id_pieza', $request->id_pieza);
            }
            
            // Filtrar por aprobado
            if ($request->has('aprobado') && $request->aprobado !== null) {
                $query->where('aprobado', $request->aprobado);
            }
            
            // Ordenar
            $orderBy = $request->get('order_by', 'id_detalle');
            $orderDir = $request->get('order_dir', 'desc');
            $query->orderBy($orderBy, $orderDir);
            
            // Paginación
            $perPage = $request->get('per_page', 15);
            $detalles = $query->paginate($perPage);
            
            return response()->json([
                'success' => true,
                'data' => $detalles,
                'message' => 'Detalles obtenidos correctamente'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener detalles',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Crear un nuevo detalle
     * POST /api/presupuestos-detalles
     */
    public function store(StoreDetalleRequest $request)
    {
        try {
            DB::beginTransaction();
            
            // Verificar que el presupuesto esté pendiente
            $presupuesto = Presupuesto::findOrFail($request->id_presupuesto);
            if ($presupuesto->estado !== Presupuesto::ESTADO_PENDIENTE) {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo se pueden agregar detalles a presupuestos pendientes'
                ], 422);
            }
            
            // Crear el detalle
            $detalle = PresupuestoDetalle::create([
                'id_presupuesto' => $request->id_presupuesto,
                'id_pieza' => $request->id_pieza,
                'descripcion' => $request->descripcion,
                'costo' => $request->costo,
                'aprobado' => $request->aprobado ?? false
            ]);
            
            // Recalcular total del presupuesto
            $presupuesto->recalcularTotal();
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'data' => $detalle->load(['presupuesto', 'pieza']),
                'message' => 'Detalle creado exitosamente'
            ], 201);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Presupuesto no encontrado'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el detalle',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Mostrar un detalle específico
     * GET /api/presupuestos-detalles/{id}
     */
    public function show($id)
    {
        try {
            $detalle = PresupuestoDetalle::with(['presupuesto', 'pieza'])->findOrFail($id);
            
            return response()->json([
                'success' => true,
                'data' => $detalle,
                'message' => 'Detalle obtenido correctamente'
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Detalle no encontrado'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el detalle',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Actualizar un detalle (parcial o completo)
     * PUT/PATCH /api/presupuestos-detalles/{id}
     */
    public function update(UpdateDetalleRequest $request, $id)
    {
        try {
            DB::beginTransaction();
            
            $detalle = PresupuestoDetalle::findOrFail($id);
            
            // Verificar que el presupuesto esté pendiente
            if ($detalle->presupuesto->estado !== Presupuesto::ESTADO_PENDIENTE) {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo se pueden actualizar detalles de presupuestos pendientes'
                ], 422);
            }
            
            // Actualizar solo los campos que vienen
            $camposActualizados = [];
            
            if ($request->has('id_pieza')) {
                $detalle->id_pieza = $request->id_pieza;
                $camposActualizados[] = 'id_pieza';
            }
            
            if ($request->has('descripcion')) {
                $detalle->descripcion = $request->descripcion;
                $camposActualizados[] = 'descripcion';
            }
            
            if ($request->has('costo')) {
                $detalle->costo = $request->costo;
                $camposActualizados[] = 'costo';
            }
            
            if ($request->has('aprobado')) {
                $detalle->aprobado = $request->aprobado;
                $camposActualizados[] = 'aprobado';
            }
            
            $detalle->save();
            
            // Recalcular total del presupuesto
            $detalle->presupuesto->recalcularTotal();
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'data' => $detalle->load(['presupuesto', 'pieza']),
                'campos_actualizados' => $camposActualizados,
                'message' => 'Detalle actualizado correctamente'
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Detalle no encontrado'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el detalle',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Eliminar un detalle
     * DELETE /api/presupuestos-detalles/{id}
     */
    public function destroy($id)
    {
        try {
            DB::beginTransaction();
            
            $detalle = PresupuestoDetalle::findOrFail($id);
            
            // Verificar que el presupuesto esté pendiente
            if ($detalle->presupuesto->estado !== Presupuesto::ESTADO_PENDIENTE) {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo se pueden eliminar detalles de presupuestos pendientes'
                ], 422);
            }
            
            $detalle->delete();
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Detalle eliminado correctamente'
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Detalle no encontrado'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el detalle',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Aprobar o desaprobar un detalle específico
     * PATCH /api/presupuestos-detalles/{id}/aprobar
     */
    public function toggleAprobado($id)
    {
        try {
            DB::beginTransaction();
            
            $detalle = PresupuestoDetalle::findOrFail($id);
            
            // Verificar que el presupuesto esté pendiente
            if ($detalle->presupuesto->estado !== Presupuesto::ESTADO_PENDIENTE) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede cambiar el estado de aprobación'
                ], 422);
            }
            
            // Cambiar estado de aprobación
            $detalle->aprobado = !$detalle->aprobado;
            $detalle->save();
            
            // Recalcular total (aunque el total no cambia por aprobación)
            $detalle->presupuesto->recalcularTotal();
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'data' => $detalle,
                'message' => $detalle->aprobado ? 'Detalle aprobado' : 'Detalle desaprobado'
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Detalle no encontrado'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al cambiar estado del detalle',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Obtener estadísticas de detalles
     * GET /api/presupuestos-detalles/estadisticas/resumen
     */
    public function estadisticas(Request $request)
    {
        try {
            $query = PresupuestoDetalle::query();
            
            // Filtrar por presupuesto si viene
            if ($request->has('id_presupuesto')) {
                $query->where('id_presupuesto', $request->id_presupuesto);
            }
            
            $stats = [
                'total_detalles' => $query->count(),
                'aprobados' => (clone $query)->where('aprobado', true)->count(),
                'no_aprobados' => (clone $query)->where('aprobado', false)->count(),
                'sin_definir' => (clone $query)->whereNull('aprobado')->count(),
                'costo_total' => (clone $query)->sum('costo'),
                'costo_promedio' => (clone $query)->avg('costo') ?? 0,
                'costo_minimo' => (clone $query)->min('costo') ?? 0,
                'costo_maximo' => (clone $query)->max('costo') ?? 0
            ];
            
            // Top 5 piezas más usadas
            $topPiezas = PresupuestoDetalle::select('id_pieza', DB::raw('COUNT(*) as total'))
                ->with('pieza')
                ->groupBy('id_pieza')
                ->orderBy('total', 'desc')
                ->limit(5)
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'estadisticas' => $stats,
                    'top_piezas' => $topPiezas
                ],
                'message' => 'Estadísticas obtenidas correctamente'
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
     * Copiar detalles de un presupuesto a otro
     * POST /api/presupuestos-detalles/copiar
     */
    public function copiarDetalles(Request $request)
    {
        try {
            $request->validate([
                'origen_id' => 'required|exists:presupuesto,id_presupuesto',
                'destino_id' => 'required|exists:presupuesto,id_presupuesto'
            ]);
            
            DB::beginTransaction();
            
            $origen = Presupuesto::findOrFail($request->origen_id);
            $destino = Presupuesto::findOrFail($request->destino_id);
            
            // Verificar que destino esté pendiente
            if ($destino->estado !== Presupuesto::ESTADO_PENDIENTE) {
                return response()->json([
                    'success' => false,
                    'message' => 'El presupuesto destino debe estar pendiente'
                ], 422);
            }
            
            // Copiar detalles
            $detallesCopiados = 0;
            foreach ($origen->detalles as $detalle) {
                PresupuestoDetalle::create([
                    'id_presupuesto' => $destino->id_presupuesto,
                    'id_pieza' => $detalle->id_pieza,
                    'descripcion' => $detalle->descripcion,
                    'costo' => $detalle->costo,
                    'aprobado' => false
                ]);
                $detallesCopiados++;
            }
            
            // Recalcular total del destino
            $destino->recalcularTotal();
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'detalles_copiados' => $detallesCopiados,
                    'presupuesto_destino' => $destino->load('detalles')
                ],
                'message' => "$detallesCopiados detalles copiados correctamente"
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al copiar detalles',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}