<?php

namespace App\Http\Controllers;

use App\Models\Presupuesto;
use App\Models\PresupuestoDetalle;
use App\Http\Requests\Presupuesto\StorePresupuestoRequest;
use App\Http\Requests\Presupuesto\UpdatePresupuestoRequest;
use App\Http\Requests\Presupuesto\AprobarPresupuestoRequest;
use App\Http\Requests\PresupuestoDetalle\StoreDetalleRequest;
use App\Http\Requests\PresupuestoDetalle\UpdateDetalleRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PresupuestoController extends Controller
{
    /**
     * Listar todos los presupuestos con filtros opcionales
     * GET /api/presupuestos
     */
   public function index(Request $request)
{
    try {
        $query = Presupuesto::with(['usuario', 'ingreso', 'detalles']);
        
        if ($request->has('estado') && $request->estado) {
            $query->where('estado', $request->estado);
        }
        
        if ($request->has('id_usuario') && $request->id_usuario) {
            $query->where('id_usuario', $request->id_usuario);
        }
        
        if ($request->has('id_ingreso') && $request->id_ingreso) {
            $query->where('id_ingreso', $request->id_ingreso);
        }

        // ✅ NUEVO — filtrar por dispositivo a través de ingreso
        if ($request->has('id_dispositivo') && $request->id_dispositivo) {
            $query->whereHas('ingreso', function($q) use ($request) {
                $q->where('id_dispositivo', $request->id_dispositivo);
            });
        }
        
        if ($request->has('fecha_desde') && $request->fecha_desde) {
            $query->whereDate('fecha_creacion', '>=', $request->fecha_desde);
        }
        
        if ($request->has('fecha_hasta') && $request->fecha_hasta) {
            $query->whereDate('fecha_creacion', '<=', $request->fecha_hasta);
        }
        
        $orderBy  = $request->get('order_by', 'fecha_creacion');
        $orderDir = $request->get('order_dir', 'desc');
        $query->orderBy($orderBy, $orderDir);
        
        // ✅ Sin paginación para consultas específicas de cliente
        if ($request->has('id_dispositivo') || $request->has('id_ingreso')) {
            return response()->json([
                'success' => true,
                'data'    => $query->get(),
                'message' => 'Presupuestos obtenidos correctamente'
            ]);
        }

        // Paginación para listados generales
        $presupuestos = $query->paginate($request->get('per_page', 15));
        
        return response()->json([
            'success' => true,
            'data'    => $presupuestos,
            'message' => 'Presupuestos obtenidos correctamente'
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error al obtener presupuestos',
            'error'   => $e->getMessage()
        ], 500);
    }
}

public function aprobar(AprobarPresupuestoRequest $request, $id)
{
    try {
        DB::beginTransaction();
        
        $presupuesto = Presupuesto::findOrFail($id);
        
        // ✅ CORREGIDO — acepta PENDIENTE y CALCULADO
        if (!in_array($presupuesto->estado, [
            Presupuesto::ESTADO_PENDIENTE,
            Presupuesto::ESTADO_CALCULADO
        ])) {
            return response()->json([
                'success' => false,
                'message' => 'El presupuesto ya fue procesado. Estado actual: ' . $presupuesto->estado
            ], 422);
        }
        
        if ($request->accion === 'APROBAR') {
            if ($request->has('detalles_aprobados') && count($request->detalles_aprobados) > 0) {
                PresupuestoDetalle::whereIn('id_detalle', $request->detalles_aprobados)
                    ->update(['aprobado' => true]);
            }
            $presupuesto->estado = Presupuesto::ESTADO_APROBADO;
            $mensaje = 'Presupuesto aprobado exitosamente';
        } else {
            $presupuesto->estado = Presupuesto::ESTADO_RECHAZADO;
            $mensaje = 'Presupuesto rechazado';
        }
        
        $presupuesto->save();
        DB::commit();
        
        return response()->json([
            'success' => true,
            'data'    => $presupuesto->load('detalles'),
            'message' => $mensaje
        ]);
        
    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'success' => false,
            'message' => 'Error al procesar el presupuesto',
            'error'   => $e->getMessage()
        ], 500);
    }
}
    /**
     * Crear un nuevo presupuesto
     * POST /api/presupuestos
     */
    public function store(StorePresupuestoRequest $request)
    {
        try {
            DB::beginTransaction();
            
            // Crear el presupuesto
            $presupuesto = Presupuesto::create([
                'id_ingreso' => $request->id_ingreso,
                'id_usuario' => $request->id_usuario,
                'estado' => Presupuesto::ESTADO_PENDIENTE,
                'total_estimado' => $request->total_estimado ?? 0,
                'fecha_validez' => $request->fecha_validez
            ]);
            
            // Crear detalles si existen
            if ($request->has('detalles') && count($request->detalles) > 0) {
                foreach ($request->detalles as $detalle) {
                    PresupuestoDetalle::create([
                        'id_presupuesto' => $presupuesto->id_presupuesto,
                        'id_pieza' => $detalle['id_pieza'],
                        'descripcion' => $detalle['descripcion'] ?? null,
                        'costo' => $detalle['costo'],
                        'aprobado' => false
                    ]);
                }
                
                // Recalcular total automáticamente
                $presupuesto->recalcularTotal();
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'data' => $presupuesto->load(['detalles.pieza', 'usuario', 'ingreso']),
                'message' => 'Presupuesto creado exitosamente'
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el presupuesto',
                'error' => $e->getMessage()
            ], 500);
        }
    }
   public function presupuestosPendientesPorTecnico(Request $request)
{
    $tecnicoId = $request->input('id_usuario');

    $presupuestos = Presupuesto::where('estado', 'PENDIENTE')
        ->where('id_usuario', $tecnicoId) // ✅ busca directo en presupuestos
        ->with(['ingreso.dispositivo.cliente',
         'detalles',
         'ingreso.dispositivo.modelo.marca'])
        ->get();

    return response()->json([
        'success' => true,
        'data' => $presupuestos
    ]);
}
    
    /**
     * Mostrar un presupuesto específico
     * GET /api/presupuestos/{id}
     */
    public function show($id)
    {
        try {
            $presupuesto = Presupuesto::with([
                'detalles.pieza', 
                'usuario', 
                'ingreso'
            ])->findOrFail($id);
            
            return response()->json([
                'success' => true,
                'data' => $presupuesto,
                'message' => 'Presupuesto obtenido correctamente'
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Presupuesto no encontrado'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el presupuesto',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Actualizar un presupuesto (parcial o completo)
     * PUT/PATCH /api/presupuestos/{id}
     */
    public function update(UpdatePresupuestoRequest $request, $id)
{
    try {
        DB::beginTransaction();
        
        $presupuesto = Presupuesto::findOrFail($id);
        
        // ✅ ACTUALIZADO: Permitir solo PENDIENTE (no CALCULADO)
        if (!in_array($presupuesto->estado, [Presupuesto::ESTADO_PENDIENTE])) {
            return response()->json([
                'success' => false,
                'message' => 'Solo se pueden actualizar presupuestos en estado PENDIENTE. Estado actual: ' . $presupuesto->estado
            ], 422);
        }
        
        // Resto del código igual...
        $camposActualizados = [];
        
        if ($request->has('estado')) {
            // No permitir cambiar a APROBADO/RECHAZADO directamente si está CALCULADO?
            // Mejor manejarlo con el método aprobar()
            if ($request->estado !== Presupuesto::ESTADO_PENDIENTE) {
                return response()->json([
                    'success' => false,
                    'message' => 'Use el endpoint /aprobar para cambiar el estado a APROBADO/RECHAZADO'
                ], 422);
            }
            $presupuesto->estado = $request->estado;
            $camposActualizados[] = 'estado';
        }
        
        if ($request->has('fecha_validez')) {
            $presupuesto->fecha_validez = $request->fecha_validez;
            $camposActualizados[] = 'fecha_validez';
        }
        
        if ($request->has('total_estimado')) {
            $presupuesto->total_estimado = $request->total_estimado;
            $camposActualizados[] = 'total_estimado';
        }
        
        $presupuesto->save();
        
        DB::commit();
        
        return response()->json([
            'success' => true,
            'data' => $presupuesto->load(['detalles', 'usuario']),
            'campos_actualizados' => $camposActualizados,
            'message' => 'Presupuesto actualizado correctamente'
        ]);
        
    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Presupuesto no encontrado'
        ], 404);
    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'success' => false,
            'message' => 'Error al actualizar el presupuesto',
            'error' => $e->getMessage()
        ], 500);
    }
}
    

    /**
 * Reabrir un presupuesto calculado (solo admin)
 * POST /api/presupuestos/{id}/reabrir
 */
public function reabrir($id)
{
    try {
        DB::beginTransaction();
        
        $presupuesto = Presupuesto::findOrFail($id);
        
        // Verificar que esté CALCULADO (no APROBADO/RECHAZADO)
        if ($presupuesto->estado !== Presupuesto::ESTADO_CALCULADO) {
            return response()->json([
                'success' => false,
                'message' => 'Solo se pueden reabrir presupuestos en estado CALCULADO. Estado actual: ' . $presupuesto->estado
            ], 422);
        }
        
        // Aquí podrías verificar si el usuario es admin
        // if (!auth()->user()->hasRole('admin')) {
        //     return response()->json(['success' => false, 'message' => 'No autorizado'], 403);
        // }
        
        $presupuesto->estado = Presupuesto::ESTADO_PENDIENTE;
        $presupuesto->save();
        
        DB::commit();
        
        return response()->json([
            'success' => true,
            'data' => $presupuesto,
            'message' => 'Presupuesto reabierto correctamente. Ahora se puede modificar.'
        ]);
        
    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'success' => false,
            'message' => 'Error al reabrir el presupuesto',
            'error' => $e->getMessage()
        ], 500);
    }
}
    /**
     * Eliminar un presupuesto
     * DELETE /api/presupuestos/{id}
     */
    public function destroy($id)
    {
        try {
            DB::beginTransaction();
            
            $presupuesto = Presupuesto::findOrFail($id);
            
            // Verificar que esté pendiente
            if ($presupuesto->estado !== Presupuesto::ESTADO_PENDIENTE) {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo se pueden eliminar presupuestos en estado PENDIENTE'
                ], 422);
            }
            
            $presupuesto->delete();
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Presupuesto eliminado correctamente'
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Presupuesto no encontrado'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el presupuesto',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
  
    
    /**
     * Agregar un detalle a un presupuesto existente
     * POST /api/presupuestos/detalles
     */
    public function addDetalle(StoreDetalleRequest $request)
    {
        try {
            DB::beginTransaction();
            
            $detalle = PresupuestoDetalle::create([
                'id_presupuesto' => $request->id_presupuesto,
                'id_pieza' => $request->id_pieza,
                'descripcion' => $request->descripcion,
                'costo' => $request->costo,
                'aprobado' => $request->aprobado ?? false
            ]);
            
            // Recalcular total del presupuesto
            $detalle->presupuesto->recalcularTotal();
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'data' => $detalle->load('pieza'),
                'message' => 'Detalle agregado correctamente'
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al agregar el detalle',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Actualizar un detalle específico
     * PUT/PATCH /api/presupuestos/detalles/{id}
     */
    public function updateDetalle(UpdateDetalleRequest $request, $id)
    {
        try {
            DB::beginTransaction();
            
            $detalle = PresupuestoDetalle::findOrFail($id);
            
            // Verificar que el presupuesto esté pendiente
            if ($detalle->presupuesto->estado !== Presupuesto::ESTADO_PENDIENTE) {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo se pueden modificar detalles de presupuestos pendientes'
                ], 422);
            }
            
            // Actualizar solo los campos que vienen
            $camposActualizados = [];
            
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
                'data' => $detalle->load('pieza'),
                'campos_actualizados' => $camposActualizados,
                'message' => 'Detalle actualizado correctamente'
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
                'message' => 'Error al actualizar el detalle',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Eliminar un detalle
     * DELETE /api/presupuestos/detalles/{id}
     */
    public function deleteDetalle($id)
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
    public function calcular($id)
{
    try {
        DB::beginTransaction();
        
        $presupuesto = Presupuesto::findOrFail($id);
        
        // Verificar que esté PENDIENTE
        if ($presupuesto->estado !== Presupuesto::ESTADO_PENDIENTE) {
            return response()->json([
                'success' => false,
                'message' => 'Solo se pueden calcular presupuestos en estado PENDIENTE. Estado actual: ' . $presupuesto->estado
            ], 422);
        }
        
        // Verificar que tenga al menos un detalle
        $detalles = $presupuesto->detalles;
        if ($detalles->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede calcular un presupuesto sin piezas agregadas'
            ], 422);
        }
        
        // Recalcular total por si acaso
        $total = $presupuesto->recalcularTotal();
        
        // Cambiar estado a CALCULADO
        $presupuesto->estado = Presupuesto::ESTADO_CALCULADO;
        $presupuesto->save();
        
        DB::commit();
        
        return response()->json([
            'success' => true,
            'data' => $presupuesto->load('detalles'),
            'total' => $total,
            'message' => 'Presupuesto calculado y finalizado correctamente. Ya no se puede modificar.'
        ]);
        
    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Presupuesto no encontrado'
        ], 404);
    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'success' => false,
            'message' => 'Error al calcular el presupuesto',
            'error' => $e->getMessage()
        ], 500);
    }
}
    
    /**
     * Obtener estadísticas de presupuestos
     * GET /api/presupuestos/estadisticas/resumen
     */
    public function estadisticas(Request $request)
    {
        try {
            $stats = [
                'total' => Presupuesto::count(),
                'pendientes' => Presupuesto::where('estado', Presupuesto::ESTADO_PENDIENTE)->count(),
                'aprobados' => Presupuesto::where('estado', Presupuesto::ESTADO_APROBADO)->count(),
                'rechazados' => Presupuesto::where('estado', Presupuesto::ESTADO_RECHAZADO)->count(),
                'total_estimado_general' => Presupuesto::sum('total_estimado'),
                'promedio_por_presupuesto' => Presupuesto::avg('total_estimado') ?? 0
            ];
            
            // Estadísticas por mes
            $stats_por_mes = Presupuesto::select(
                DB::raw('DATE_TRUNC(\'month\', fecha_creacion) as mes'),
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(total_estimado) as suma')
            )
            ->groupBy(DB::raw('DATE_TRUNC(\'month\', fecha_creacion)'))
            ->orderBy('mes', 'desc')
            ->limit(6)
            ->get();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'resumen' => $stats,
                    'por_mes' => $stats_por_mes
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
}