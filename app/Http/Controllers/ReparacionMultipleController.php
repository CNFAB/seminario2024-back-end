<?php

namespace App\Http\Controllers;

use App\Models\ReparacionMultiple;
use App\Models\Pieza;
use App\Models\Reparacion;
use App\Models\Diagnostico;
use App\Models\Usuario;
use App\Http\Requests\ReparacionMultiple\StoreRequestReparacionM;
use App\Http\Requests\ReparacionMultiple\UpdateRequestReparacionM;
use App\Models\Garantia;       
use App\Models\GarantiaPieza; 
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReparacionMultipleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = ReparacionMultiple::with([
                'reparacion:id_reparacion,id_diagnostico,id_usuario,id_ingreso,comentario',
                'reparacion.tecnico:id_usuario,nombre,apellido',
                'reparacion.diagnostico:id_diagnostico,observacion,estado',
                'reparacion.ingreso:id_ingreso,fecha_ingreso,id_dispositivo',
                'reparacion.ingreso.dispositivo:id_dispositivo,imei,codigo_interno',
                'pieza:id_pieza,nombre_pieza,precio,id_categoria',
                'pieza.categoria:id_categoria,categoria,mano_obra'
            ]);

            if ($request->has('id_reparacion'))  $query->where('id_reparacion', $request->id_reparacion);
            if ($request->has('id_pieza'))        $query->where('id_pieza',       $request->id_pieza);
            if ($request->has('estado'))          $query->where('estado',          $request->estado);

            if ($request->has('tecnico_id')) {
                $query->whereHas('reparacion', fn($q) => $q->where('id_usuario', $request->tecnico_id));
            }
            if ($request->has('diagnostico_id')) {
                $query->whereHas('reparacion', fn($q) => $q->where('id_diagnostico', $request->diagnostico_id));
            }
            if ($request->has('ingreso_id')) {
                $query->whereHas('reparacion', fn($q) => $q->where('id_ingreso', $request->ingreso_id));
            }
            if ($request->has('fecha_inicio_desde') && $request->has('fecha_inicio_hasta')) {
                $query->whereBetween('fecha_ini_reparacion', [$request->fecha_inicio_desde, $request->fecha_inicio_hasta]);
            }
            if ($request->has('fecha_fin_desde') && $request->has('fecha_fin_hasta')) {
                $query->whereBetween('fecha_fin_reparacion', [$request->fecha_fin_desde, $request->fecha_fin_hasta]);
            }
            if ($request->has('precio_min')) $query->where('precio_total', '>=', $request->precio_min);
            if ($request->has('precio_max')) $query->where('precio_total', '<=', $request->precio_max);

            if ($request->has('search')) {
                $term = $request->search;
                $query->where(function ($q) use ($term) {
                    $q->where('comentario_tecnico', 'ILIKE', "%{$term}%")
                      ->orWhereHas('pieza', fn($q2) => $q2->where('nombre_pieza', 'ILIKE', "%{$term}%"))
                      ->orWhereHas('reparacion.ingreso.dispositivo', fn($q2) => $q2->where('imei', 'ILIKE', "%{$term}%")->orWhere('codigo_interno', 'ILIKE', "%{$term}%"));
                });
            }

            if ($request->get('ordenar_por_prioridad') === 'true') {
                $query->orderByRaw("CASE estado
                    WHEN 'EN_REPARACION'   THEN 1
                    WHEN 'PENDIENTE'       THEN 2
                    WHEN 'ESPERANDO_PIEZA' THEN 3
                    WHEN 'TERMINADO'       THEN 4
                    WHEN 'CANCELADO'       THEN 5
                    ELSE 6 END");
            } else {
                $allowed = ['id_multiple', 'id_reparacion', 'id_pieza', 'estado', 'fecha_ini_reparacion', 'fecha_fin_reparacion', 'precio_total'];
                $sortBy  = in_array($request->get('sort_by'), $allowed) ? $request->get('sort_by') : 'id_multiple';
                $query->orderBy($sortBy, $request->get('sort_order', 'desc'));
            }

            if ($request->get('paginate') === 'false') {
                $data = $query->get();
                return response()->json(['success' => true, 'count' => $data->count(), 'data' => $data]);
            }

            $paginated = $query->paginate($request->get('per_page', 15));

            return response()->json([
                'success'      => true,
                'current_page' => $paginated->currentPage(),
                'data'         => $paginated->items(),
                'total'        => $paginated->total(),
                'per_page'     => $paginated->perPage(),
                'last_page'    => $paginated->lastPage(),
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener reparaciones múltiples', ['error' => $e->getMessage()]);
            return $this->errorResponse('Error al obtener las reparaciones múltiples', $e);
        }
    }

public function store(StoreRequestReparacionM $request): JsonResponse
{
    DB::beginTransaction();
    try {
        \Log::info('=== STORE ReparacionMultiple INICIO ===', [
            'request_data' => $request->validated(),
            'id_reparacion' => $request->id_reparacion,
            'id_pieza' => $request->id_pieza,
        ]);

        $reparacion = Reparacion::find($request->id_reparacion);
        if (!$reparacion) {
            \Log::warning('Reparación padre no encontrada', ['id_reparacion' => $request->id_reparacion]);
            return $this->notFoundResponse('La reparación padre no existe');
        }
        \Log::info('Reparación padre encontrada', [
            'id_reparacion' => $reparacion->id_reparacion,
            'es_garantia'   => $reparacion->es_garantia,
            'estado'        => $reparacion->estado,
        ]);

        $pieza = Pieza::with('categoria')->find($request->id_pieza);
        if (!$pieza) {
            \Log::warning('Pieza no encontrada', ['id_pieza' => $request->id_pieza]);
            return $this->notFoundResponse('La pieza no existe');
        }
        \Log::info('Pieza encontrada', [
            'id_pieza'    => $pieza->id_pieza,
            'nombre'      => $pieza->nombre_pieza,
            'stock_actual' => $pieza->stock,
            'stock_es_null' => is_null($pieza->stock),
            'precio'      => $pieza->precio,
            'categoria'   => $pieza->categoria?->categoria,
            'mano_obra'   => $pieza->categoria?->mano_obra,
        ]);

        $stockActual = $pieza->stock ?? 0;
        if ($stockActual < 1) {
            \Log::warning('Stock insuficiente - bloqueando creación', [
                'id_pieza'    => $pieza->id_pieza,
                'stock_valor' => $pieza->stock,
                'stock_usado' => $stockActual,
            ]);
            return response()->json([
                'success'      => false,
                'message'      => 'No hay stock disponible para esta pieza',
                'stock_actual' => $stockActual,
            ], 400);
        }

        \Log::info('Stock OK - procediendo a decrementar', [
            'id_pieza'      => $pieza->id_pieza,
            'stock_antes'   => $pieza->stock,
        ]);

        $pieza->decrement('stock', 1);
        $pieza->refresh();

        \Log::info('Stock decrementado', [
            'id_pieza'     => $pieza->id_pieza,
            'stock_despues' => $pieza->stock,
        ]);

        $datos = $request->validated();
        $esGarantia = $reparacion->es_garantia ?? false;

        \Log::info('Determinando tipo de reparación', [
            'es_garantia' => $esGarantia,
        ]);

        if ($esGarantia) {
            $datos['estado'] = 'EN_REPARACION';
            $datos['fecha_ini_reparacion'] = now();
            $datos['estado_pago'] = 'PAGADO';
            \Log::info('Reparación de GARANTÍA - estado forzado a EN_REPARACION');
        } else {
            $datos['estado'] = $datos['estado'] ?? 'PENDIENTE';
            \Log::info('Reparación NORMAL - estado asignado', ['estado' => $datos['estado']]);
        }

        $precioPieza        = $pieza->precio ?? 0;
        $manoObraPorcentaje = $pieza->categoria->mano_obra ?? 0;
        $costoTotal         = $precioPieza + ($precioPieza * ($manoObraPorcentaje / 100));

        $datos['precio_pieza_momento'] = $precioPieza;
        $datos['mano_obra_momento']    = $manoObraPorcentaje;
        $datos['precio_total']         = round($costoTotal, 2);

        \Log::info('Precios calculados', [
            'precio_pieza'    => $precioPieza,
            'mano_obra_%'     => $manoObraPorcentaje,
            'costo_total'     => round($costoTotal, 2),
        ]);

        \Log::info('Datos finales antes de crear', ['datos' => $datos]);

        $reparacionMultiple = ReparacionMultiple::create($datos);

        \Log::info('ReparacionMultiple creada exitosamente', [
            'id_multiple'    => $reparacionMultiple->id_multiple,
            'id_reparacion'  => $reparacionMultiple->id_reparacion,
            'es_garantia'    => $esGarantia,
            'estado'         => $reparacionMultiple->estado,
            'id_pieza'       => $pieza->id_pieza,
            'precio_total'   => $reparacionMultiple->precio_total,
            'stock_final'    => $pieza->stock,
        ]);

        DB::commit();
        \Log::info('=== STORE ReparacionMultiple COMMIT OK ===');

        $mensaje = $esGarantia
            ? 'Pieza agregada a la reparación de garantía (iniciando automáticamente)'
            : 'Reparación múltiple creada exitosamente';

        return response()->json([
            'success' => true,
            'message' => $mensaje,
            'data'    => $reparacionMultiple->load(['reparacion', 'pieza.categoria']),
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        \Log::error('=== STORE ReparacionMultiple ERROR ===', [
            'error' => $e->getMessage(),
            'file'  => $e->getFile(),
            'line'  => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);
        return $this->errorResponse('Error al crear la reparación múltiple', $e);
    }
}

    public function show($id): JsonResponse
    {
        try {
            $rm = ReparacionMultiple::with([
                'reparacion.tecnico',
                'reparacion.diagnostico',
                'reparacion.ingreso.dispositivo.cliente',
                'pieza.categoria'
            ])->findOrFail($id);

            return response()->json(['success' => true, 'data' => $rm]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->notFoundResponse('Reparación múltiple no encontrada');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener la reparación múltiple', $e);
        }
    }

/**
 * Actualizar reparación múltiple (con control especial para garantías)
 */
public function update(UpdateRequestReparacionM $request, $id): JsonResponse
{
    DB::beginTransaction();
    try {
        $rm = ReparacionMultiple::with(['reparacion', 'pieza.categoria'])->findOrFail($id);
        $datos = $request->validated();
        
        // ✅ OBTENER es_garantia DESDE LA REPARACION PADRE
        $esGarantia = $rm->reparacion->es_garantia ?? false;
        
        // ============================================
        // CONTROL DE ESTADOS PARA GARANTÍAS
        // ============================================
        if ($esGarantia && isset($datos['estado'])) {
            // Solo estados permitidos para garantías
            $estadosPermitidos = ['EN_REPARACION', 'TERMINADO', 'CANCELADO'];
            
            if (!in_array($datos['estado'], $estadosPermitidos)) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Las reparaciones de garantía solo pueden estar en: EN_REPARACION, TERMINADO o CANCELADO',
                    'estado_solicitado' => $datos['estado']
                ], 400);
            }
            
            // Bloquear estados de aprobación
            if (in_array($datos['estado'], ['PENDIENTE', 'ESPERANDO_APROBACION', 'APROBADO', 'RECHAZADO'])) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Las reparaciones de garantía no requieren aprobación. Use EN_REPARACION directamente.'
                ], 400);
            }
        }
        
        // ============================================
        // CAMBIO DE PIEZA (si aplica)
        // ============================================
        if (isset($datos['id_pieza']) && $datos['id_pieza'] != $rm->id_pieza) {
            // Devolver stock de la pieza anterior
            if ($rm->id_pieza) {
                optional(Pieza::find($rm->id_pieza))->increment('stock', 1);
            }

            $piezaNueva = Pieza::with('categoria')->find($datos['id_pieza']);
            if (!$piezaNueva) { 
                DB::rollBack(); 
                return $this->notFoundResponse('La nueva pieza no existe'); 
            }
            
            if ($piezaNueva->stock < 1) { 
                DB::rollBack(); 
                return response()->json(['success' => false, 'message' => 'Sin stock para la nueva pieza'], 400); 
            }
            
            $piezaNueva->decrement('stock', 1);
            
            // Recalcular precios
            $precioPieza = $piezaNueva->precio ?? 0;
            $manoObraPorcentaje = $piezaNueva->categoria->mano_obra ?? 0;
            $costoTotal = $precioPieza + ($precioPieza * ($manoObraPorcentaje / 100));
            
            $datos['precio_pieza_momento'] = $precioPieza;
            $datos['mano_obra_momento'] = $manoObraPorcentaje;
            $datos['precio_total'] = round($costoTotal, 2);
            
            // ✅ Si es garantía y cambia la pieza, asegurar que sigue en EN_REPARACION
            if ($esGarantia) {
                $datos['estado'] = 'EN_REPARACION';
                if (!$rm->fecha_ini_reparacion) {
                    $datos['fecha_ini_reparacion'] = now();
                }
            }
        }
        
        // ============================================
        // MANEJO DE FECHAS SEGÚN ESTADO
        // ============================================
        if (isset($datos['estado'])) {
            if ($datos['estado'] === 'EN_REPARACION' && !$rm->fecha_ini_reparacion) {
                $datos['fecha_ini_reparacion'] = now();
            }
            
            if ($datos['estado'] === 'TERMINADO' && !$rm->fecha_fin_reparacion) {
                $datos['fecha_fin_reparacion'] = now();
            }
            
            if ($datos['estado'] === 'CANCELADO' && !$rm->fecha_fin_reparacion) {
                $datos['fecha_fin_reparacion'] = now();
                optional(Pieza::find($rm->id_pieza))->increment('stock', 1);
            }
        }
        
        // Aplicar cambios
        $rm->fill($datos);
        
        if (!$rm->isDirty()) {
            DB::rollBack();
            return response()->json(['success' => true, 'message' => 'Sin cambios detectados', 'data' => $rm]);
        }
        
        $cambios = $rm->getDirty();
        $rm->save();
        
        // ============================================
        // ACCIONES POST-ACTUALIZACIÓN
        // ============================================
        
        // Si es garantía y se terminó la pieza
        if ($esGarantia && isset($cambios['estado']) && $rm->estado === 'TERMINADO') {
            $todasTerminadas = $this->verificarTodasPiezasTerminadas($rm->id_reparacion);
            
            if ($todasTerminadas) {
                // Marcar reparación padre como TERMINADO
                $reparacionPadre = $rm->reparacion;
                $reparacionPadre->estado = 'TERMINADO';
                $reparacionPadre->save();
                
                // Si tiene diagnóstico, marcarlo como LISTO_PARA_RETIRAR
                if ($reparacionPadre->diagnostico) {
                    $reparacionPadre->diagnostico->estado = 'LISTO_PARA_RETIRAR';
                    $reparacionPadre->diagnostico->save();
                }
                
                DB::commit();
                
                // Notificar al cliente (fuera de la transacción)
                $this->notificarClienteRetiro($reparacionPadre);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Reparación de garantía completada. Cliente notificado.',
                    'data' => $rm->load(['reparacion', 'pieza.categoria']),
                    'cambios_realizados' => $cambios,
                ]);
            }
        }
        
        DB::commit();
        
        // Solo para reparaciones normales, actualizar diagnóstico
        if (!$esGarantia) {
            $this->verificarYActualizarDiagnostico($rm->id_reparacion);
        }
        
        Log::info('ReparacionMultiple actualizada', [
            'id_multiple' => $rm->id_multiple,
            'es_garantia' => $esGarantia,
            'cambios' => $cambios,
            'nuevo_estado' => $rm->estado
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Reparación múltiple actualizada exitosamente',
            'data' => $rm->load(['reparacion', 'pieza.categoria']),
            'cambios_realizados' => $cambios,
        ]);
        
    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        DB::rollBack();
        return $this->notFoundResponse('Reparación múltiple no encontrada');
    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Error al actualizar reparación múltiple', [
            'id' => $id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        return $this->errorResponse('Error al actualizar la reparación múltiple', $e);
    }
}

    public function destroy($id): JsonResponse
    {
        DB::beginTransaction();
        try {
            $rm = ReparacionMultiple::findOrFail($id);

            if ($rm->estado !== 'PENDIENTE') {
                return response()->json(['success' => false, 'message' => 'Solo se pueden eliminar reparaciones en estado PENDIENTE'], 400);
            }

            optional(Pieza::find($rm->id_pieza))->increment('stock', 1);
            $rm->delete();
            DB::commit();

            return response()->json(['success' => true, 'message' => 'Reparación múltiple eliminada exitosamente']);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return $this->notFoundResponse('Reparación múltiple no encontrada');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al eliminar la reparación múltiple', $e);
        }
    }
public function resumenPorIngreso($idIngreso): JsonResponse
{
    try {
        \Log::info('=== resumenPorIngreso ===', ['id_ingreso' => $idIngreso]);
        
        // ✅ Buscar reparación por id_ingreso directo O a través de diagnóstico
        $reparacion = Reparacion::where(function($query) use ($idIngreso) {
                $query->where('id_ingreso', $idIngreso)
                      ->orWhereHas('diagnostico', function($q) use ($idIngreso) {
                          $q->where('id_ingreso', $idIngreso);
                      });
            })
            ->with([
                'ingreso.dispositivo.modelo.marca',
                'diagnostico.ingreso.dispositivo.modelo.marca',
                'reparacionesMultiples.pieza:id_pieza,nombre_pieza,precio',
                'reparacionesMultiples.pieza.categoria:id_categoria,categoria,mano_obra',
            ])
            ->first();

        if (!$reparacion) {
            \Log::warning('Reparación no encontrada', ['id_ingreso' => $idIngreso]);
            return response()->json([
                'success' => true,
                'data' => [
                    'tiene_reparaciones' => false,
                    'mensaje' => 'No hay reparaciones asociadas a este ingreso'
                ]
            ]);
        }

        // ✅ OBTENER DISPOSITIVO
        $dispositivo = null;
        $imei = null;
        $nombreDispositivo = '';
        
        $dispositivo = $reparacion->dispositivo;
        
        if (!$dispositivo) {
            if ($reparacion->id_diagnostico && $reparacion->diagnostico && $reparacion->diagnostico->ingreso) {
                $dispositivo = $reparacion->diagnostico->ingreso->dispositivo;
            } elseif ($reparacion->id_ingreso && $reparacion->ingreso) {
                $dispositivo = $reparacion->ingreso->dispositivo;
            }
        }
        
        if ($dispositivo) {
            $imei = $dispositivo->imei ?? $dispositivo->codigo_interno ?? null;
            if ($dispositivo->modelo) {
                $nombreDispositivo = trim(
                    ($dispositivo->modelo->marca->marca ?? '') . ' ' . 
                    ($dispositivo->modelo->nombre_modelo ?? '')
                );
            }
        }

        if (!$dispositivo) {
            \Log::warning('No se encontró dispositivo para reparación', [
                'id_reparacion' => $reparacion->id_reparacion,
                'id_ingreso_reparacion' => $reparacion->id_ingreso,
                'id_diagnostico' => $reparacion->id_diagnostico,
                'id_ingreso_buscado' => $idIngreso
            ]);
        }

        // ✅ FILTRAR: Solo piezas activas (NO rechazadas ni canceladas)
        $todasLasPiezas = $reparacion->reparacionesMultiples;
        $piezasActivas = $todasLasPiezas->filter(function($pieza) {
            return !in_array($pieza->estado, ['RECHAZADO', 'CANCELADO']);
        })->values();
        
        // ✅ Calcular total SOLO con piezas activas
        $total = $piezasActivas->sum('precio_total');
        
        $estadoReparacion = $reparacion->estado_general;
        $estadoPagoReparacion = $reparacion->estado_pago ?? 'PENDIENTE';
        
        // Determinar si está listo para retirar (solo si hay piezas activas)
        $listoParaRetirar = false;
        if ($piezasActivas->count() > 0) {
            if ($reparacion->id_diagnostico && $reparacion->diagnostico) {
                $listoParaRetirar = $reparacion->diagnostico->estado === 'LISTO_PARA_RETIRAR';
            } else {
                $listoParaRetirar = $estadoReparacion === 'TERMINADO';
            }
        }

        $yaPagado = in_array($estadoPagoReparacion, ['PAGADO', 'PAGADO_LOCAL']);

        return response()->json([
            'success' => true,
            'data' => [
                'tiene_reparaciones' => $piezasActivas->count() > 0,
                'id_reparacion' => $reparacion->id_reparacion,
                'es_garantia' => (bool) $reparacion->es_garantia,
                'id_ingreso' => $idIngreso,
                'tiene_diagnostico' => $reparacion->tieneDiagnostico(),
                'estado_reparacion' => $estadoReparacion,
                'diagnostico_estado' => $reparacion->diagnostico ? $reparacion->diagnostico->estado : null,
                'dispositivo_nombre' => $nombreDispositivo ?: 'Dispositivo no encontrado',
                'imei' => $imei ?: 'No registrado',
                // ✅ Enviar SOLO piezas activas
                'piezas' => $piezasActivas->map(function($pieza) {
                    return [
                        'id_multiple' => $pieza->id_multiple,
                        'id_pieza' => $pieza->id_pieza,
                        'nombre_pieza' => $pieza->pieza->nombre_pieza ?? 'Pieza no encontrada',
                        'precio_pieza_momento' => $pieza->precio_pieza_momento,
                        'mano_obra_momento' => $pieza->mano_obra_momento,
                        'precio_total' => $pieza->precio_total,
                        'estado_pieza' => $pieza->estado,
                        'estado_pago' => $pieza->estado_pago ?? 'PENDIENTE',
                        'comentario_tecnico' => $pieza->comentario_tecnico,
                    ];
                }),
                'total_piezas' => $piezasActivas->count(),
                'piezas_terminadas' => $piezasActivas->where('estado', 'TERMINADO')->count(),
                'costo_total' => $total,
                'estado_pago_general' => $estadoPagoReparacion,
                'listo_para_retirar' => $listoParaRetirar,
                'ya_pagado' => $yaPagado
            ]
        ]);

    } catch (\Exception $e) {
        \Log::error('Error en resumenPorIngreso', [
            'id_ingreso' => $idIngreso,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        return response()->json([
            'success' => false,
            'message' => 'Error al obtener resumen de reparaciones: ' . $e->getMessage()
        ], 500);
    }
}
/**
 * Obtener reparaciones CANCELADAS por dispositivo
 */
public function getCanceladasPorDispositivo($idDispositivo): JsonResponse
{
    try {
        $reparaciones = Reparacion::with([
            'reparacionesMultiples.pieza:id_pieza,nombre_pieza,precio,id_categoria',
            'reparacionesMultiples.pieza.categoria:id_categoria,categoria,mano_obra',
            'ingreso:id_ingreso,fecha_ingreso,estado,id_dispositivo',
            'diagnostico.ingreso:id_ingreso,fecha_ingreso,id_dispositivo'
        ])
        ->where('estado', 'CANCELADO')
        ->where(function($q) use ($idDispositivo) {
            // Reparaciones por ingreso directo
            $q->whereHas('ingreso', function($sub) use ($idDispositivo) {
                $sub->where('id_dispositivo', $idDispositivo);
            })
            // O reparaciones por diagnóstico
            ->orWhereHas('diagnostico.ingreso', function($sub) use ($idDispositivo) {
                $sub->where('id_dispositivo', $idDispositivo);
            });
        })
        ->get();

        foreach ($reparaciones as $reparacion) {
            if ($reparacion->ingreso) {
                $reparacion->fecha_ingreso = $reparacion->ingreso->fecha_ingreso;
                $reparacion->dispositivo = $reparacion->ingreso->dispositivo;
            } elseif ($reparacion->diagnostico && $reparacion->diagnostico->ingreso) {
                $reparacion->fecha_ingreso = $reparacion->diagnostico->ingreso->fecha_ingreso;
                $reparacion->dispositivo = $reparacion->diagnostico->ingreso->dispositivo;
            }
        }

        return response()->json([
            'success' => true,
            'data' => $reparaciones
        ]);

    } catch (\Exception $e) {
        \Log::error('Error en getCanceladasPorDispositivo', [
            'id_dispositivo' => $idDispositivo,
            'error' => $e->getMessage()
        ]);
        return response()->json(['success' => false, 'data' => []], 500);
    }
}
public function getEnProgresoPorDispositivo($idDispositivo): JsonResponse
{
    try {
        $estadosEnProgreso = ['APROBADO', 'EN_REPARACION', 'ESPERANDO_PIEZA'];
        $estadosFinales    = ['TERMINADO', 'CANCELADO', 'RECHAZADO'];

        $baseWith = [
            'reparacionesMultiples.pieza:id_pieza,nombre_pieza,precio,id_categoria',
            'reparacionesMultiples.pieza.categoria:id_categoria,categoria,mano_obra',
            'ingreso:id_ingreso,fecha_ingreso,id_dispositivo',
            'diagnostico.ingreso:id_ingreso,fecha_ingreso,id_dispositivo',
        ];

        $porDispositivo = function ($q) use ($idDispositivo) {
            $q->whereHas('ingreso', fn($sub) => $sub->where('id_dispositivo', $idDispositivo))
              ->orWhereHas('diagnostico.ingreso', fn($sub) => $sub->where('id_dispositivo', $idDispositivo));
        };

        // ── CASO 1: DIRECTA (sin diagnóstico, sin garantía) ─────────────────────
        $directas = Reparacion::with($baseWith)
          ->whereNull('id_diagnostico')
          ->where(function($q) { $q->where('es_garantia', false)->orWhereNull('es_garantia'); })

            ->whereHas('reparacionesMultiples', fn($q) => $q->whereIn('estado', $estadosEnProgreso))
            ->whereDoesntHave('reparacionesMultiples', fn($q) => $q->whereIn('estado', ['ESPERANDO_APROBACION', 'PENDIENTE']))
            ->whereHas('reparacionesMultiples', fn($q) => $q->whereNotIn('estado', $estadosFinales))
            ->where($porDispositivo)
            ->get();

        // ── CASO 2: DESDE DIAGNÓSTICO (con diagnóstico, sin garantía) ───────────
        $conDiagnostico = Reparacion::with($baseWith)
            ->whereNotNull('id_diagnostico')
            ->where('es_garantia', false)
            ->whereHas('reparacionesMultiples', fn($q) => $q->whereIn('estado', $estadosEnProgreso))
            ->whereHas('reparacionesMultiples', fn($q) => $q->whereNotIn('estado', $estadosFinales))
            ->where($porDispositivo)
            ->get();

        // ── CASO 3: GARANTÍA (con diagnóstico, es_garantia = true) ──────────────
        $garantias = Reparacion::with($baseWith)
            ->whereNull('id_diagnostico')
            ->where('es_garantia', true)
            ->whereHas('reparacionesMultiples', fn($q) => $q->whereIn('estado', $estadosEnProgreso))
            ->whereHas('reparacionesMultiples', fn($q) => $q->whereNotIn('estado', $estadosFinales))
            ->whereHas('ingreso', fn($q) => $q->where('id_dispositivo', $idDispositivo))
            ->get();
        // ── Merge, fecha_ingreso y devolver ─────────────────────────────────────
        $todas = $directas->merge($conDiagnostico)->merge($garantias);

        foreach ($todas as $rep) {
            $rep->fecha_ingreso = $rep->ingreso?->fecha_ingreso
                ?? $rep->diagnostico?->ingreso?->fecha_ingreso;
        }

        return response()->json(['success' => true, 'data' => $todas->values()]);

    } catch (\Exception $e) {
        Log::error('Error getEnProgresoPorDispositivo', [
            'id_dispositivo' => $idDispositivo,
            'error' => $e->getMessage()
        ]);
        return response()->json(['success' => false, 'data' => []], 500);
    }
}


    public function enviarAprobacion($id) {
    $reparacion = ReparacionMultiple::find($id);
    $reparacion->update(['estado' => 'ESPERANDO_APROBACION']);
    return response()->json(['success' => true]);
}
    public function aprobarPieza($id): JsonResponse
{
    DB::beginTransaction();
    try {
        $rm = ReparacionMultiple::with([
            'reparacion:id_reparacion,id_diagnostico,id_ingreso,estado',
            'reparacion.ingreso:id_ingreso,id_dispositivo'
        ])->findOrFail($id);

        if ($rm->reparacion->id_diagnostico !== null) {
            return response()->json(['success' => false, 'message' => 'Esta reparación viene de un diagnóstico'], 400);
        }

        if ($rm->estado !== 'ESPERANDO_APROBACION') {
            return response()->json(['success' => false, 'message' => 'La pieza no está esperando aprobación'], 400);
        }

        $rm->estado = 'APROBADO';
        $rm->save();

        // Verificar si todas las piezas fueron respondidas
        $todasPiezas      = ReparacionMultiple::where('id_reparacion', $rm->id_reparacion)->get();
        $todasRespondidas = $todasPiezas->every(fn($p) => in_array($p->estado, ['APROBADO', 'RECHAZADO']));

        if ($todasRespondidas) {
            $algunaAceptada = $todasPiezas->some(fn($p) => $p->estado === 'APROBADO');
            $rm->reparacion->estado = $algunaAceptada ? 'EN_REPARACION' : 'CANCELADO';
            $rm->reparacion->save();
        }

        DB::commit();

        return response()->json(['success' => true, 'message' => 'Pieza aprobada', 'data' => $rm->fresh()]);

    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        DB::rollBack();
        return $this->notFoundResponse('Pieza no encontrada');
    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Error al aprobar pieza', ['error' => $e->getMessage()]);
        return $this->errorResponse('Error al aprobar la pieza', $e);
    }
}

public function rechazarPieza($id): JsonResponse
{
    DB::beginTransaction();
    try {
        $rm = ReparacionMultiple::with([
            'reparacion:id_reparacion,id_diagnostico,id_ingreso,estado'
        ])->findOrFail($id);

        if ($rm->reparacion->id_diagnostico !== null) {
            return response()->json(['success' => false, 'message' => 'Esta reparación viene de un diagnóstico'], 400);
        }

        if ($rm->estado !== 'ESPERANDO_APROBACION') {
            return response()->json(['success' => false, 'message' => 'La pieza no está esperando aprobación'], 400);
        }

        $rm->estado = 'RECHAZADO';
        $rm->save();

        optional(Pieza::find($rm->id_pieza))->increment('stock', 1);

        $todasPiezas      = ReparacionMultiple::where('id_reparacion', $rm->id_reparacion)->get();
        $todasRespondidas = $todasPiezas->every(fn($p) => in_array($p->estado, ['APROBADO', 'RECHAZADO']));

        if ($todasRespondidas) {
            $algunaAceptada = $todasPiezas->some(fn($p) => $p->estado === 'APROBADO');
            $rm->reparacion->estado = $algunaAceptada ? 'EN_REPARACION' : 'CANCELADO';
            $rm->reparacion->save();
        }

        DB::commit();

        return response()->json(['success' => true, 'message' => 'Pieza rechazada', 'data' => $rm->fresh()]);

    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        DB::rollBack();
        return $this->notFoundResponse('Pieza no encontrada');
    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Error al rechazar pieza', ['error' => $e->getMessage()]);
        return $this->errorResponse('Error al rechazar la pieza', $e);
    }
}
public function cambiarEstado(Request $request, $id): JsonResponse
{
    DB::beginTransaction();
    try {
        $request->validate([
            'estado' => 'required|string|in:PENDIENTE,EN_REPARACION,TERMINADO,ESPERANDO_PIEZA,CANCELADO,ESPERANDO_APROBACION,APROBADO,RECHAZADO'
        ]);

        $rm = ReparacionMultiple::findOrFail($id);
        
        $estadoAnterior = $rm->estado;
        $rm->estado = $request->estado;
        
        // Actualizar fechas según estado
        if ($request->estado === 'EN_REPARACION' && !$rm->fecha_ini_reparacion) {
            $rm->fecha_ini_reparacion = now();
        }
        if ($request->estado === 'TERMINADO' && !$rm->fecha_fin_reparacion) {
            $rm->fecha_fin_reparacion = now();
        }
        
        $rm->save();
        DB::commit();

        Log::info('Estado de pieza actualizado', [
            'id_multiple' => $id,
            'estado_anterior' => $estadoAnterior,
            'nuevo_estado' => $request->estado,
            'por_usuario' => auth()->id()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Estado actualizado correctamente',
            'data' => $rm
        ]);

    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        DB::rollBack();
        return $this->notFoundResponse('Pieza no encontrada');
    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Error al cambiar estado', ['error' => $e->getMessage()]);
        return $this->errorResponse('Error al cambiar el estado', $e);
    }
}
public function getPendientesPorDispositivo($idDispositivo): JsonResponse
{
    try {
        $reparaciones = Reparacion::with([
            'reparacionesMultiples.pieza:id_pieza,nombre_pieza,precio,id_categoria',
            'reparacionesMultiples.pieza.categoria:id_categoria,categoria,mano_obra',
            'ingreso:id_ingreso,fecha_ingreso,id_dispositivo',
            'ingreso.dispositivo:id_dispositivo,imei,codigo_interno,id_modelo',
            'ingreso.dispositivo.modelo:id_modelo,nombre_modelo,id_marca',
            'ingreso.dispositivo.modelo.marca:id_marca,marca',
            'diagnostico.ingreso:id_ingreso,fecha_ingreso,id_dispositivo',
            'diagnostico.ingreso.dispositivo:id_dispositivo,imei,codigo_interno,id_modelo',
            'diagnostico.ingreso.dispositivo.modelo:id_modelo,nombre_modelo,id_marca',
            'diagnostico.ingreso.dispositivo.modelo.marca:id_marca,marca'
        ])
        ->whereHas('reparacionesMultiples', function($q) {
            $q->whereIn('estado', ['PENDIENTE', 'ESPERANDO_APROBACION']);
        })
        ->where(function($q) use ($idDispositivo) {
            $q->whereHas('diagnostico.ingreso', function($sub) use ($idDispositivo) {
                $sub->where('id_dispositivo', $idDispositivo);
            })
            ->orWhereHas('ingreso', function($sub) use ($idDispositivo) {
                $sub->where('id_dispositivo', $idDispositivo);
            });
        })
        ->get();

        foreach ($reparaciones as $reparacion) {
            if ($reparacion->ingreso) {
                $reparacion->fecha_ingreso = $reparacion->ingreso->fecha_ingreso;
                // También puedes asignar el dispositivo para el frontend
                $reparacion->dispositivo = $reparacion->ingreso->dispositivo;
            } elseif ($reparacion->diagnostico && $reparacion->diagnostico->ingreso) {
                $reparacion->fecha_ingreso = $reparacion->diagnostico->ingreso->fecha_ingreso;
                $reparacion->dispositivo = $reparacion->diagnostico->ingreso->dispositivo;
            }
        }

        return response()->json(['success' => true, 'data' => $reparaciones]);

    } catch (\Exception $e) {
        Log::error('Error al obtener reparaciones pendientes', [
            'id_dispositivo' => $idDispositivo,
            'error' => $e->getMessage()
        ]);
        return response()->json(['success' => false, 'data' => []], 500);
    }
}



    // ============================================
    // CONSULTAS ESPECÍFICAS
    // ============================================

    public function getByReparacionId($id_reparacion): JsonResponse
    {
        try {
            $items = ReparacionMultiple::with([
                'pieza:id_pieza,nombre_pieza,precio,id_categoria',
                'pieza.categoria:id_categoria,categoria,mano_obra'
            ])
            ->where('id_reparacion', $id_reparacion)
            ->orderBy('id_multiple', 'asc')
            ->get();

            return response()->json([
                'success'       => true,
                'data'          => $items,
                'count'         => $items->count(),
                'id_reparacion' => $id_reparacion
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener sub-reparaciones', $e);
        }
    }

    public function porReparacion($idReparacion): JsonResponse
    {
        return $this->getByReparacionId($idReparacion);
    }

    public function misReparaciones(Request $request): JsonResponse
    {
        try {
            $tecnicoId = auth()->id();

            $query = ReparacionMultiple::with([
                'reparacion:id_reparacion,id_diagnostico,id_usuario,id_ingreso',
                'pieza:id_pieza,nombre_pieza,precio',
                'reparacion.tecnico:id_usuario,nombre,apellido'
            ])
            ->whereHas('reparacion', fn($q) => $q->where('id_usuario', $tecnicoId))
            ->orderByRaw("CASE estado
                WHEN 'EN_REPARACION'   THEN 1
                WHEN 'PENDIENTE'       THEN 2
                WHEN 'ESPERANDO_PIEZA' THEN 3
                WHEN 'TERMINADO'       THEN 4
                WHEN 'CANCELADO'       THEN 5
                ELSE 6 END")
            ->orderBy('fecha_ini_reparacion', 'desc');

            if ($request->has('estado'))        $query->where('estado',        $request->estado);
            if ($request->has('id_reparacion')) $query->where('id_reparacion', $request->id_reparacion);

            if ($request->has('fecha_desde') && $request->has('fecha_hasta')) {
                $query->whereBetween('fecha_ini_reparacion', [$request->fecha_desde, $request->fecha_hasta]);
            }

            return response()->json(['success' => true, 'data' => $query->paginate(20)]);

        } catch (\Exception $e) {
            Log::error('Error al obtener reparaciones del técnico', ['tecnico_id' => auth()->id(), 'error' => $e->getMessage()]);
            return $this->errorResponse('Error al obtener las reparaciones', $e);
        }
    }

    // ============================================
    // ACCIONES RÁPIDAS
    // ============================================

public function comenzar($id): JsonResponse
{
    DB::beginTransaction();
    try {
        $rm = ReparacionMultiple::with(
            'reparacion.diagnostico.ingreso.dispositivo.cliente',
            'reparacion.diagnostico.ingreso.dispositivo.modelo.marca',
            'pieza'
        )->findOrFail($id);

        if (!in_array($rm->estado, ['PENDIENTE', 'ESPERANDO_PIEZA', 'APROBADO'])) {
            return response()->json([
                'success' => false, 
                'message' => 'Solo se puede comenzar una reparación en estado PENDIENTE, ESPERANDO_PIEZA o APROBADO'
            ], 400);
        }

        // ✅ DESCONTAR STOCK al comenzar
        $pieza = Pieza::find($rm->id_pieza);
        if ($pieza) {
            $stockActual = $pieza->stock ?? 0;
            if ($stockActual < 1) {
                DB::rollBack();
                return response()->json([
                    'success'      => false,
                    'message'      => "Sin stock disponible para la pieza: {$pieza->nombre_pieza}",
                    'stock_actual' => $stockActual,
                ], 400);
            }

            $pieza->decrement('stock', 1);
            $pieza->refresh();

            \Log::info('Stock decrementado al comenzar reparación', [
                'id_multiple'   => $rm->id_multiple,
                'id_pieza'      => $pieza->id_pieza,
                'nombre_pieza'  => $pieza->nombre_pieza,
                'stock_despues' => $pieza->stock,
            ]);
        } else {
            \Log::warning('Pieza no encontrada al comenzar reparación', [
                'id_multiple' => $rm->id_multiple,
                'id_pieza'    => $rm->id_pieza,
            ]);
        }

        $estadoAnterior = $rm->estado;
        $rm->update([
            'estado'               => 'EN_REPARACION',
            'fecha_ini_reparacion' => $rm->fecha_ini_reparacion ?? now(),
        ]);

        $cliente = $rm->reparacion?->diagnostico?->ingreso?->dispositivo?->cliente;
        if ($cliente && $estadoAnterior !== $rm->estado) {
            try {
                $cliente->notify(new \App\Notifications\ReparacionMultipleEstadoChanged(
                    $rm, $estadoAnterior, $rm->estado
                ));
            } catch (\Exception $e) {
                \Log::error('❌ Error notificación inicio', [
                    'id_multiple' => $rm->id_multiple, 
                    'error'       => $e->getMessage()
                ]);
            }
        }

        DB::commit();

        Log::info('ReparacionMultiple iniciada', [
            'id_multiple' => $rm->id_multiple, 
            'por'         => auth()->id()
        ]);

        return response()->json([
            'success' => true, 
            'message' => 'Reparación iniciada', 
            'data'    => $rm->fresh()
        ]);

    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        DB::rollBack();
        return $this->notFoundResponse('Reparación múltiple no encontrada');
    } catch (\Exception $e) {
        DB::rollBack();
        return $this->errorResponse('Error al iniciar la reparación', $e);
    }
}

public function terminar(Request $request, $id): JsonResponse
{
    DB::beginTransaction();
    try {
        \Log::info('=== INICIO TERMINAR ===', ['id' => $id]);
        
        $request->validate(['comentario_tecnico' => 'nullable|string|max:200']);

        $rm = ReparacionMultiple::with([
            'reparacion.diagnostico.ingreso.dispositivo.cliente',
            'reparacion.diagnostico.ingreso.dispositivo.modelo.marca',
            'pieza'
        ])->findOrFail($id);

        \Log::info('Estado actual de la pieza', [
            'id_multiple'    => $rm->id_multiple,
            'estado_actual'  => $rm->estado,
            'isEnReparacion' => $rm->isEnReparacion()
        ]);

        if ($rm->estado !== 'EN_REPARACION') {
            \Log::warning('La pieza no está en estado EN_REPARACION', [
                'estado_actual' => $rm->estado
            ]);
            return response()->json([
                'success'      => false,
                'message'      => 'Solo se puede terminar una reparación en estado EN_REPARACION',
                'estado_actual' => $rm->estado
            ], 400);
        }

        $estadoAnterior = $rm->estado;

        if (!$rm->precio_total) {
            $rm->calcularPrecioTotal();
        }

        $rm->estado               = 'TERMINADO';
        $rm->fecha_fin_reparacion = now();
        $rm->comentario_tecnico   = $request->comentario_tecnico;
        $rm->save();

        \Log::info('Pieza actualizada a TERMINADO', [
            'id_multiple' => $rm->id_multiple,
            'nuevo_estado' => $rm->estado,
            'fecha_fin'    => $rm->fecha_fin_reparacion
        ]);

        // Verificar si todas las piezas están terminadas
        $todasPiezasTerminadas = $this->verificarTodasPiezasTerminadas($rm->id_reparacion);
        \Log::info('Todas las piezas terminadas?', ['resultado' => $todasPiezasTerminadas]);

        if ($todasPiezasTerminadas) {
            \Log::info('Llamando a crearGarantiaParaReparacion', [
                'id_reparacion' => $rm->id_reparacion
            ]);
            $this->crearGarantiaParaReparacion($rm->id_reparacion); // ✅ Solo una vez
        } else {
            \Log::info('NO se llama a crearGarantia - faltan piezas por terminar');
        }

        // Notificar al cliente
        $cliente = $rm->reparacion?->diagnostico?->ingreso?->dispositivo?->cliente;
        if ($cliente && $estadoAnterior !== $rm->estado) {
            try {
                $cliente->notify(new \App\Notifications\ReparacionMultipleEstadoChanged(
                    $rm, $estadoAnterior, $rm->estado
                ));
            } catch (\Exception $e) {
                \Log::error('Error notificación finalización', ['error' => $e->getMessage()]);
            }
        }

        DB::commit();

        $this->verificarYActualizarDiagnostico($rm->id_reparacion);

        return response()->json([
            'success' => true,
            'message' => 'Reparación terminada',
            'data'    => $rm->fresh()
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        \Log::error('Error en terminar', [
            'id'    => $id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        return $this->errorResponse('Error al terminar la reparación', $e);
    }
}

    public function retomarDesdEsperaPieza($id): JsonResponse
{
    DB::beginTransaction();
    try {
        $rm = ReparacionMultiple::with(
            'reparacion.diagnostico.ingreso.dispositivo.cliente',
            'reparacion.diagnostico.ingreso.dispositivo.modelo.marca'
        )->findOrFail($id);

        if ($rm->estado !== 'ESPERANDO_PIEZA') {
            return response()->json([
                'success' => false,
                'message' => 'Solo se puede retomar desde estado ESPERANDO_PIEZA',
                'estado_actual' => $rm->estado
            ], 400);
        }

        $estadoAnterior = $rm->estado;

        // ✅ Solo cambia el estado, SIN tocar el stock (ya fue descontado en comenzar)
        $rm->estado = 'EN_REPARACION';
        $rm->save();

        \Log::info('Reparación retomada desde ESPERANDO_PIEZA', [
            'id_multiple'    => $rm->id_multiple,
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo'   => $rm->estado,
            'por'            => auth()->id(),
        ]);

        // Notificación al cliente
        $cliente = $rm->reparacion?->diagnostico?->ingreso?->dispositivo?->cliente;
        if ($cliente) {
            try {
                $cliente->notify(new \App\Notifications\ReparacionMultipleEstadoChanged(
                    $rm, $estadoAnterior, $rm->estado
                ));
            } catch (\Exception $e) {
                \Log::error('Error notificación retomar', [
                    'id_multiple' => $rm->id_multiple,
                    'error'       => $e->getMessage()
                ]);
            }
        }

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Reparación retomada correctamente',
            'data'    => $rm->fresh()
        ]);

    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        DB::rollBack();
        return $this->notFoundResponse('Reparación múltiple no encontrada');
    } catch (\Exception $e) {
        DB::rollBack();
        \Log::error('Error al retomar reparación', [
            'id'    => $id,
            'error' => $e->getMessage()
        ]);
        return $this->errorResponse('Error al retomar la reparación', $e);
    }
}
    
    public function getTerminadasPorDispositivo($idDispositivo): JsonResponse
{
    try {
        $reparaciones = Reparacion::with([
            'reparacionesMultiples.pieza:id_pieza,nombre_pieza,precio,id_categoria',
            'reparacionesMultiples.pieza.categoria:id_categoria,categoria,mano_obra',
            'ingreso:id_ingreso,fecha_ingreso,estado,id_dispositivo',
            'diagnostico.ingreso:id_ingreso,fecha_ingreso,id_dispositivo'
        ])
        // ✅ ELIMINAR el whereNull('id_diagnostico') - traer TODAS las reparaciones
        ->whereHas('reparacionesMultiples', function($q) {
            $q->where('estado', 'TERMINADO');
        })
        ->whereDoesntHave('reparacionesMultiples', function($q) {
            $q->whereNotIn('estado', ['TERMINADO', 'CANCELADO', 'RECHAZADO']);
        })
        ->where(function($q) use ($idDispositivo) {
            // Reparaciones por ingreso directo
            $q->whereHas('ingreso', function($sub) use ($idDispositivo) {
                $sub->where('id_dispositivo', $idDispositivo);
            })
            // O reparaciones por diagnóstico
            ->orWhereHas('diagnostico.ingreso', function($sub) use ($idDispositivo) {
                $sub->where('id_dispositivo', $idDispositivo);
            });
        })
        ->get();

        foreach ($reparaciones as $reparacion) {
            if ($reparacion->ingreso) {
                $reparacion->fecha_ingreso = $reparacion->ingreso->fecha_ingreso;
                $reparacion->dispositivo = $reparacion->ingreso->dispositivo;
            } elseif ($reparacion->diagnostico && $reparacion->diagnostico->ingreso) {
                $reparacion->fecha_ingreso = $reparacion->diagnostico->ingreso->fecha_ingreso;
                $reparacion->dispositivo = $reparacion->diagnostico->ingreso->dispositivo;
            }
        }

        return response()->json(['success' => true, 'data' => $reparaciones]);

    } catch (\Exception $e) {
        \Log::error('Error en getTerminadasPorDispositivo', [
            'id_dispositivo' => $idDispositivo,
            'error' => $e->getMessage()
        ]);
        return response()->json(['success' => false, 'data' => []], 500);
    }
}
/**
 * Verificar si todas las piezas de una reparación están TERMINADAS
 */
private function verificarTodasPiezasTerminadas(int $idReparacion): bool
{
    // Obtener solo las piezas que NO fueron rechazadas o canceladas
    $piezasActivas = ReparacionMultiple::where('id_reparacion', $idReparacion)
        ->whereNotIn('estado', ['RECHAZADO', 'CANCELADO'])
        ->get();
    
    if ($piezasActivas->isEmpty()) {
        // Si solo hay piezas rechazadas/canceladas, no hay nada que terminar
        \Log::info('verificarTodasPiezasTerminadas: No hay piezas activas', [
            'id_reparacion' => $idReparacion
        ]);
        return false;
    }
    
    // Verificar que TODAS las piezas activas estén TERMINADO
    $todasTerminadas = $piezasActivas->every(fn($p) => $p->estado === 'TERMINADO');
    
    \Log::info('verificarTodasPiezasTerminadas', [
        'id_reparacion' => $idReparacion,
        'piezas_activas' => $piezasActivas->count(),
        'estados' => $piezasActivas->pluck('estado')->toArray(),
        'todas_terminadas' => $todasTerminadas ? 'SI' : 'NO'
    ]);
    
    return $todasTerminadas;
}
/**
 * Crear garantía automáticamente para una reparación terminada
 */
private function crearGarantiaParaReparacion(int $idReparacion): void
{
    try {
        \Log::info('=== INICIO crearGarantiaParaReparacion ===', ['id_reparacion' => $idReparacion]);
        
        $garantiaExistente = Garantia::where('id_reparacion', $idReparacion)->first();
        if ($garantiaExistente) {
            \Log::info('Ya existe garantía - saliendo');
            return;
        }
        
        $reparacion = Reparacion::with([
            'diagnostico.ingreso.dispositivo.cliente',
            'ingreso.dispositivo.cliente'
        ])->find($idReparacion);
        
        if (!$reparacion) {
            \Log::error('❌ Reparación no encontrada', ['id' => $idReparacion]);
            return;
        }
        
        \Log::info('Reparación encontrada', [
            'id_reparacion' => $reparacion->id_reparacion,
            'id_diagnostico' => $reparacion->id_diagnostico,
            'id_ingreso' => $reparacion->id_ingreso,
            'tiene_diagnostico' => $reparacion->id_diagnostico ? 'SI' : 'NO',
            'tiene_ingreso' => $reparacion->id_ingreso ? 'SI' : 'NO'
        ]);
        
        // 👉 Obtener el cliente
        $cliente = null;
        if ($reparacion->diagnostico && $reparacion->diagnostico->ingreso) {
            $cliente = $reparacion->diagnostico->ingreso->dispositivo->cliente ?? null;
            \Log::info('Cliente desde diagnóstico', ['cliente_id' => $cliente?->id_cliente]);
        } elseif ($reparacion->ingreso) {
            $cliente = $reparacion->ingreso->dispositivo->cliente ?? null;
            \Log::info('Cliente desde ingreso directo', ['cliente_id' => $cliente?->id_cliente]);
        } else {
            \Log::warning('⚠️ No se encontró cliente');
        }
        
        // Obtener piezas
        $piezas = ReparacionMultiple::where('id_reparacion', $idReparacion)
            ->with(['pieza.categoria'])
            ->get();
        
        \Log::info('Piezas encontradas', ['cantidad' => $piezas->count()]);
        
        if ($piezas->isEmpty()) {
            \Log::warning('⚠️ No hay piezas para crear garantía');
            return;
        }
        
        $diasGarantiaMax = $piezas->max(function($p) {
            return $p->pieza?->categoria?->garantia_dias ?? 90;
        });
        
        $diasGarantiaMax = max($diasGarantiaMax, 15);
        $mesesGarantia = max(1, ceil($diasGarantiaMax / 30));
        
        \Log::info('Creando garantía', [
            'dias' => $diasGarantiaMax,
            'meses' => $mesesGarantia
        ]);
        
        $garantia = Garantia::create([
            'id_reparacion' => $idReparacion,
            'fecha_inicio' => now(),
            'fecha_fin' => now()->addDays($diasGarantiaMax),
            'duracion_meses' => $mesesGarantia,
            'duracion_dias' => $diasGarantiaMax,
            'tipo_garantia' => Garantia::TIPO_ESTANDAR,
            'estado' => Garantia::ESTADO_ACTIVA,
            'comentario' => 'Garantía automática por finalización de reparación',
            'created_by' => auth()->id()
        ]);
        
        \Log::info('✅ Garantía creada', ['id_garantia' => $garantia->id_garantia]);
        
        $this->generarGarantiaPiezas($garantia);
        
    } catch (\Exception $e) {
        \Log::error('❌ Error en crearGarantiaParaReparacion', [
            'id_reparacion' => $idReparacion,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
}
/**
 * Generar garantías por pieza
 */
private function generarGarantiaPiezas($garantia): void
{
    $existentes = GarantiaPieza::where('id_garantia', $garantia->id_garantia)->count();
    if ($existentes > 0) {
        \Log::info('La garantía ya tiene piezas asignadas', [
            'id_garantia' => $garantia->id_garantia,
            'piezas_existentes' => $existentes
        ]);
        return;
    }
    
    $reparacionMultiples = ReparacionMultiple::where('id_reparacion', $garantia->id_reparacion)
        ->whereNotIn('estado', ['RECHAZADO', 'CANCELADO'])
        ->with(['pieza.categoria'])
        ->get();
    
    $fechasFin = [];
    $diasGarantiaMax = 0;
    
    foreach ($reparacionMultiples as $rm) {
        $pieza = $rm->pieza;
        $categoria = $pieza ? $pieza->categoria : null;
        
        $diasGarantia = $categoria && $categoria->garantia_dias ? $categoria->garantia_dias : 90;
        
        $fechaInicio = \Carbon\Carbon::parse($garantia->fecha_inicio);
        $fechaFin = $fechaInicio->copy()->addDays($diasGarantia);
        
        GarantiaPieza::create([
            'id_garantia' => $garantia->id_garantia,
            'id_reparacion_multiple' => $rm->id_multiple,
            'id_pieza' => $pieza ? $pieza->id_pieza : null,
            'id_categoria' => $categoria ? $categoria->id_categoria : null,
            'garantia_dias_asignados' => $diasGarantia,
            'fecha_inicio' => $garantia->fecha_inicio,
            'fecha_fin' => $fechaFin,
            'estado' => GarantiaPieza::ESTADO_ACTIVA
        ]);
        
        $fechasFin[] = $fechaFin;
        if ($diasGarantia > $diasGarantiaMax) {
            $diasGarantiaMax = $diasGarantia;
        }
    }
    
    // ✅ Actualizar garantía principal SOLO si es necesario y si los valores son válidos
    if (!empty($fechasFin) && $diasGarantiaMax > 0) {
        $fechaFinMax = max($fechasFin);
        $mesesGarantia = max(1, ceil($diasGarantiaMax / 30));
        
        $garantia->fecha_fin = $fechaFinMax;
        $garantia->duracion_dias = $diasGarantiaMax;
        $garantia->duracion_meses = $mesesGarantia;
        $garantia->save();
        
        \Log::info('Garantía principal actualizada', [
            'id_garantia' => $garantia->id_garantia,
            'dias' => $diasGarantiaMax,
            'meses' => $mesesGarantia
        ]);
    }
}
    public function esperarPieza(Request $request, $id): JsonResponse
    {
        DB::beginTransaction();
        try {
            $request->validate(['comentario_tecnico' => 'nullable|string|max:200']);

            $rm = ReparacionMultiple::with(
                'reparacion.diagnostico.ingreso.dispositivo.cliente',
                'reparacion.diagnostico.ingreso.dispositivo.modelo.marca',
                'pieza'
            )->findOrFail($id);

            if ($rm->isTerminado() || $rm->isCancelado()) {
                return response()->json(['success' => false, 'message' => 'No se puede cambiar el estado de una reparación terminada o cancelada'], 400);
            }

            $estadoAnterior = $rm->estado;

            $rm->update([
                'estado'             => 'ESPERANDO_PIEZA',
                'comentario_tecnico' => $request->comentario_tecnico
            ]);

            $cliente = $rm->reparacion?->diagnostico?->ingreso?->dispositivo?->cliente;

            if ($cliente && $estadoAnterior !== $rm->estado) {
                try {
                    $cliente->notify(new \App\Notifications\ReparacionMultipleEstadoChanged($rm, $estadoAnterior, $rm->estado));
                    \Log::info('✅ Notificación espera pieza enviada', ['id_multiple' => $rm->id_multiple, 'cliente_email' => $cliente->correo]);
                } catch (\Exception $e) {
                    \Log::error('❌ Error notificación espera pieza', ['id_multiple' => $rm->id_multiple, 'error' => $e->getMessage()]);
                }
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Reparación marcada como esperando pieza', 'data' => $rm->fresh()]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return $this->notFoundResponse('Reparación múltiple no encontrada');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al actualizar la reparación', $e);
        }
    }

    public function cancelar(Request $request, $id): JsonResponse
    {
        DB::beginTransaction();
        try {
            $request->validate(['comentario_tecnico' => 'nullable|string|max:200']);

            $rm = ReparacionMultiple::with(
                'reparacion.diagnostico.ingreso.dispositivo.cliente',
                'reparacion.diagnostico.ingreso.dispositivo.modelo.marca',
                'pieza'
            )->findOrFail($id);

            if ($rm->isTerminado()) {
                return response()->json(['success' => false, 'message' => 'No se puede cancelar una reparación terminada'], 400);
            }

            $estadoAnterior = $rm->estado;

            $rm->update([
                'estado'               => 'CANCELADO',
                'fecha_fin_reparacion' => now(),
                'comentario_tecnico'   => $request->comentario_tecnico
            ]);

            optional(Pieza::find($rm->id_pieza))->increment('stock', 1);

            $cliente = $rm->reparacion?->diagnostico?->ingreso?->dispositivo?->cliente;

            if ($cliente && $estadoAnterior !== $rm->estado) {
                try {
                    $cliente->notify(new \App\Notifications\ReparacionMultipleEstadoChanged($rm, $estadoAnterior, $rm->estado));
                    \Log::info('✅ Notificación cancelación enviada', ['id_multiple' => $rm->id_multiple, 'cliente_email' => $cliente->correo]);
                } catch (\Exception $e) {
                    \Log::error('❌ Error notificación cancelación', ['id_multiple' => $rm->id_multiple, 'error' => $e->getMessage()]);
                }
            }

            DB::commit();
            Log::info('ReparacionMultiple cancelada', ['id_multiple' => $rm->id_multiple, 'por' => auth()->id()]);
            return response()->json(['success' => true, 'message' => 'Reparación cancelada', 'data' => $rm->fresh()]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return $this->notFoundResponse('Reparación múltiple no encontrada');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Error al cancelar la reparación', $e);
        }
    }

    // ============================================
    // ESTADÍSTICAS
    // ============================================

    public function estadisticas(Request $request): JsonResponse
    {
        try {
            $query = ReparacionMultiple::query();

            if ($request->has('tecnico_id')) {
                $query->whereHas('reparacion', fn($q) => $q->where('id_usuario', $request->tecnico_id));
            }
            if ($request->has('fecha_desde') && $request->has('fecha_hasta')) {
                $query->whereBetween('fecha_ini_reparacion', [$request->fecha_desde, $request->fecha_hasta]);
            }

            $porEstado = (clone $query)->select('estado', DB::raw('COUNT(*) as total'))
                ->groupBy('estado')->get()->pluck('total', 'estado');

            $ingresosTotales = ReparacionMultiple::where('estado', 'TERMINADO')->whereNotNull('precio_total')->sum('precio_total');

            $duracionPromedio = ReparacionMultiple::where('estado', 'TERMINADO')
                ->whereNotNull('fecha_ini_reparacion')->whereNotNull('fecha_fin_reparacion')
                ->select(DB::raw('AVG(EXTRACT(EPOCH FROM (fecha_fin_reparacion - fecha_ini_reparacion)) / 3600) as horas_promedio'))
                ->first();

            $piezasMasUsadas = DB::table('reparacion_multiple')
                ->join('pieza', 'reparacion_multiple.id_pieza', '=', 'pieza.id_pieza')
                ->select('pieza.id_pieza', 'pieza.nombre_pieza', DB::raw('COUNT(*) as veces_usada'), DB::raw('SUM(reparacion_multiple.precio_total) as total_generado'))
                ->groupBy('pieza.id_pieza', 'pieza.nombre_pieza')
                ->orderBy('veces_usada', 'desc')
                ->limit(10)->get();

            $eficienciaTecnicos = DB::table('reparacion_multiple')
                ->join('reparacion', 'reparacion_multiple.id_reparacion', '=', 'reparacion.id_reparacion')
                ->join('usuario', 'reparacion.id_usuario', '=', 'usuario.id_usuario')
                ->where('reparacion_multiple.estado', 'TERMINADO')
                ->whereNotNull('fecha_ini_reparacion')->whereNotNull('fecha_fin_reparacion')
                ->select('reparacion.id_usuario', 'usuario.nombre', 'usuario.apellido',
                    DB::raw('AVG(EXTRACT(EPOCH FROM (fecha_fin_reparacion - fecha_ini_reparacion)) / 3600) as horas_promedio'),
                    DB::raw('COUNT(*) as total_reparaciones'))
                ->groupBy('reparacion.id_usuario', 'usuario.nombre', 'usuario.apellido')
                ->orderBy('horas_promedio', 'asc')->get();

            return response()->json([
                'success' => true,
                'data'    => [
                    'resumen_general' => [
                        'total_reparaciones'        => $query->count(),
                        'total_ingresos'            => round($ingresosTotales, 2),
                        'reparaciones_por_estado'   => $porEstado,
                        'horas_promedio_reparacion' => round($duracionPromedio->horas_promedio ?? 0, 2),
                    ],
                    'piezas_mas_usadas'   => $piezasMasUsadas,
                    'eficiencia_tecnicos' => $eficienciaTecnicos,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener estadísticas de reparaciones múltiples', ['error' => $e->getMessage()]);
            return $this->errorResponse('Error al obtener estadísticas', $e);
        }
    }

    // ============================================
    // MÉTODOS PRIVADOS
    // ============================================

    private function verificarYActualizarDiagnostico(int $idReparacion): void
{
    try {
        $reparacion = Reparacion::with('diagnostico')->find($idReparacion);
        if (!$reparacion) return;

        $activas        = ReparacionMultiple::where('id_reparacion', $idReparacion)
                            ->whereNotIn('estado', ['CANCELADO', 'RECHAZADO'])->get();
        $todasTerminadas = $activas->isNotEmpty() && $activas->every(fn($rm) => $rm->estado === 'TERMINADO');

        if ($todasTerminadas) {
            // Actualizar estado de la reparacion
            $reparacion->estado = 'TERMINADO';
            $reparacion->save();

            // Si tiene diagnóstico, actualizarlo también
            if ($reparacion->diagnostico && $reparacion->diagnostico->estado !== 'LISTO_PARA_RETIRAR') {
                $reparacion->diagnostico->estado = 'LISTO_PARA_RETIRAR';
                $reparacion->diagnostico->save();
            }
        }

    } catch (\Exception $e) {
        Log::error('Error en verificarYActualizarDiagnostico', ['error' => $e->getMessage()]);
    }
}

    private function notFoundResponse(string $message): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], 404);
    }

    private function errorResponse(string $message, \Exception $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'error'   => config('app.debug') ? $e->getMessage() : 'Error interno del servidor'
        ], 500);
    }
}