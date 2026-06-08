<?php

namespace App\Http\Controllers;

use App\Models\Reparacion;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Requests\Reparacion\ReparacionRequest;
use App\Http\Requests\Reparacion\UpdateReparacionRequest;

class ReparacionController extends Controller
{
    /**
     */
   public function index(Request $request): JsonResponse
{
    try {
        $query = Reparacion::with([
            'diagnostico:id_diagnostico,observacion,id_ingreso',
            'tecnico:id_usuario,nombre,apellido',
            
            // ============================================
            // RUTA 1: Reparación DIRECTA (id_ingreso)
            // ============================================
            'ingreso:id_ingreso,fecha_ingreso,id_dispositivo',
            'ingreso.dispositivo:id_dispositivo,id_modelo',
            'ingreso.dispositivo.modelo:id_modelo,nombre_modelo,id_marca',
            'ingreso.dispositivo.modelo.marca:id_marca,marca',
            
            // ============================================
            // RUTA 2: Reparación por DIAGNÓSTICO
            // ============================================
            'diagnostico.ingreso:id_ingreso,fecha_ingreso,id_dispositivo',
            'diagnostico.ingreso.dispositivo:id_dispositivo,id_modelo',
            'diagnostico.ingreso.dispositivo.modelo:id_modelo,nombre_modelo,id_marca',
            'diagnostico.ingreso.dispositivo.modelo.marca:id_marca,marca',
            
            // ============================================
            // PIEZAS
            // ============================================
            'reparacionesMultiples',
            'reparacionesMultiples.pieza:id_pieza,nombre_pieza,precio,id_categoria',
            'reparacionesMultiples.pieza.categoria:id_categoria,categoria,mano_obra'
        ]);
        
        // Filtros básicos
        if ($request->has('tecnico_id')) {
            $query->where('id_usuario', $request->tecnico_id);
        }
        
        if ($request->has('diagnostico_id')) {
            $query->where('id_diagnostico', $request->diagnostico_id);
        }
        
        if ($request->has('id_ingreso')) {
            $query->where('id_ingreso', $request->id_ingreso);
        }
        
        $query->orderBy('id_reparacion', 'desc');
        
        if ($request->has('paginate') && $request->paginate == 'false') {
            $reparaciones = $query->get();
            
            $reparaciones->each(function ($reparacion) {
                $reparacion->multiples_count = $reparacion->reparacionesMultiples->count();
            });
            
            return response()->json([
                'success' => true,
                'data' => $reparaciones,
            ]);
        } else {
            $perPage = $request->get('per_page', 15);
            $reparaciones = $query->paginate($perPage);
            
            $reparaciones->getCollection()->transform(function ($reparacion) {
                $reparacion->multiples_count = $reparacion->reparacionesMultiples->count();
                return $reparacion;
            });
            
            return response()->json([
                'success' => true,
                'data' => $reparaciones,
            ]);
        }
        
    } catch (\Exception $e) {
        \Log::error('Error al obtener reparaciones', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Error al obtener las reparaciones.',
            'error' => config('app.debug') ? $e->getMessage() : null
        ], 500);
    }
}

    /**
     * ✅ CORREGIDO: Obtener una reparación específica con sub-reparaciones completas
     */
    public function show($id): JsonResponse
    {
        try {
            $reparacion = Reparacion::with([
                'diagnostico',
                'tecnico',
                'ingreso',
                'reparacionesMultiples',  
                'reparacionesMultiples.pieza',
                'reparacionesMultiples.pieza.categoria'
            ])->find($id);
            
            if (!$reparacion) {
                return response()->json([
                    'success' => false,
                    'message' => 'Reparación no encontrada.'
                ], 404);
            }
            
            // ✅ Agregar el conteo
            $reparacion->multiples_count = $reparacion->reparacionesMultiples->count();
            
            return response()->json([
                'success' => true,
                'data' => $reparacion,
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error al obtener reparación', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener la reparación.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
  public function reparacionesActivasPorTecnico($idTecnico): JsonResponse
{
    try {
        $reparaciones = Reparacion::where('id_usuario', $idTecnico)
            ->whereNotIn('estado', ['TERMINADO', 'CANCELADO'])
            ->where(function($query) {
                $query->doesntHave('reparacionesMultiples') // No tiene piezas
                    ->orWhereHas('reparacionesMultiples', function($q) {
                        // O tiene al menos una pieza que NO está pagada
                        $q->whereNotIn('estado', ['PAGADO']);
                    });
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

        \Log::info('Reparaciones activas (excluyendo pagadas):', [
            'tecnico_id' => $idTecnico,
            'cantidad' => $reparaciones->count(),
            'reparaciones' => $reparaciones->map(function($r) {
                return [
                    'id' => $r->id_reparacion,
                    'estado' => $r->estado,
                    'piezas_estados' => $r->reparacionesMultiples->pluck('estado')
                ];
            })
        ]);

        return response()->json([
            'success' => true,
            'data' => $reparaciones
        ]);
        
    } catch (\Exception $e) {
        \Log::error('Error en reparacionesActivasPorTecnico:', [
            'tecnico_id' => $idTecnico,
            'error' => $e->getMessage()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Error al obtener las reparaciones activas'
        ], 500);
    }
}
/**
 * Obtener reparaciones por ingreso (incluyendo las que vienen de diagnóstico)
 */
public function getPorIngresoConDiagnostico($idIngreso): JsonResponse
{
    try {
        // 1. Reparaciones DIRECTAS (creadas manualmente, sin diagnóstico)
        $directas = Reparacion::where('id_ingreso', $idIngreso)
            ->with([
                'reparacionesMultiples.pieza',
                'reparacionesMultiples.pieza.categoria',
                'diagnostico',
                'ingreso'
            ])
            ->get();

        // 2. Reparaciones DESDE DIAGNÓSTICO (a través del diagnóstico)
        $desdeDiagnostico = Reparacion::whereHas('diagnostico', function($q) use ($idIngreso) {
                $q->where('id_ingreso', $idIngreso);
            })
            ->with([
                'reparacionesMultiples.pieza',
                'reparacionesMultiples.pieza.categoria',
                'diagnostico.ingreso',
                'diagnostico'
            ])
            ->get();

        // 3. Combinar ambos resultados
        $reparaciones = $directas->merge($desdeDiagnostico);
        
        // 4. Agregar el estado_general a cada reparación
        $reparaciones->each(function ($reparacion) {
            $reparacion->estado_general = $reparacion->estado_general;
        });

        return response()->json([
            'success' => true,
            'data' => $reparaciones->values() // Re-indexar
        ]);

    } catch (\Exception $e) {
        \Log::error('Error en getPorIngresoConDiagnostico', [
            'id_ingreso' => $idIngreso,
            'error' => $e->getMessage()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Error al obtener las reparaciones',
            'error' => config('app.debug') ? $e->getMessage() : null
        ], 500);
    }
}

public function tecnicosDisponiblesReparaciones(): JsonResponse
{
    try {
        $tecnicos = Usuario::tecnicos()
            ->where('activo', true)
            ->withCount(['reparaciones as carga_actual' => function($query) {
                $query->whereNotIn('estado', ['TERMINADO', 'CANCELADO'])
                    ->where(function($q) {
                        $q->doesntHave('reparacionesMultiples')
                            ->orWhereHas('reparacionesMultiples', function($subQ) {
                                $subQ->whereNotIn('estado', ['PAGADO']);
                            });
                    });
            }])
            ->orderBy('carga_actual', 'asc')
            ->get(['id_usuario', 'nombre', 'apellido', 'correo', 'en_linea']);

        return response()->json([
            'success' => true,
            'data' => $tecnicos
        ]);

    } catch (\Exception $e) {
        \Log::error('Error en tecnicosDisponiblesReparaciones', [
            'error' => $e->getMessage()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Error al obtener técnicos disponibles'
        ], 500);
    }
}

    /**
     * Crear reparación
     */
    public function store(ReparacionRequest $request): JsonResponse
    {
        try {
            $reparacion = Reparacion::create($request->validated());
            
            $reparacion->load(['diagnostico', 'tecnico', 'ingreso', 'reparacionesMultiples']);
            
            return response()->json([
                'success' => true,
                'message' => 'Reparación creada exitosamente.',
                'data' => $reparacion,
            ], 201);
            
        } catch (\Exception $e) {
            \Log::error('Error al crear reparación', [
                'data' => $request->all(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la reparación.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Actualizar reparación
     */
    public function update(UpdateReparacionRequest $request, $id): JsonResponse
    {
        try {
            $reparacion = Reparacion::find($id);
            
            if (!$reparacion) {
                return response()->json([
                    'success' => false,
                    'message' => 'Reparación no encontrada.'
                ], 404);
            }
            
            $reparacion->update($request->validated());
            
            $reparacion->load(['diagnostico', 'tecnico', 'ingreso', 'reparacionesMultiples']);
            
            return response()->json([
                'success' => true,
                'message' => 'Reparación actualizada exitosamente.',
                'data' => $reparacion,
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error al actualizar reparación', [
                'id' => $id,
                'data' => $request->all(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la reparación.',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
    // ReparacionController.php
public function reasignarMultiples(Request $request): JsonResponse
{
    $request->validate([
        'ids_reparaciones'   => 'required|array|min:1',
        'ids_reparaciones.*' => 'integer|exists:reparacion,id_reparacion',
        'id_tecnico_nuevo'   => 'required|integer|exists:usuario,id_usuario'
    ]);

    $reasignados = [];
    foreach ($request->ids_reparaciones as $id) {
        $reparacion = Reparacion::find($id);
        if ($reparacion) {
            $reparacion->id_usuario = $request->id_tecnico_nuevo;
            $reparacion->save();
            $reasignados[] = $id;
        }
    }

    return response()->json([
        'success'     => true,
        'message'     => count($reasignados) . ' reparaciones reasignadas',
        'reasignados' => $reasignados
    ]);
}
    public function porTecnico($idTecnico): JsonResponse
{
    try {
        $reparaciones = Reparacion::where('id_usuario', $idTecnico)
        ->with([
            'tecnico:id_usuario,nombre,apellido',
            'diagnostico:id_diagnostico,observacion,estado,id_ingreso', // ✅ agregar id_ingreso
            'diagnostico.ingreso:id_ingreso,fecha_ingreso,id_dispositivo', // ✅ nuevo
            'diagnostico.ingreso.dispositivo:id_dispositivo,imei,codigo_interno,id_modelo', // ✅ nuevo
            'diagnostico.ingreso.dispositivo.modelo:id_modelo,nombre_modelo,id_marca', // ✅ nuevo
            'diagnostico.ingreso.dispositivo.modelo.marca:id_marca,marca', // ✅ nuevo
            'ingreso:id_ingreso,fecha_ingreso,id_dispositivo',
            'ingreso.dispositivo:id_dispositivo,imei,codigo_interno,id_modelo',
            'ingreso.dispositivo.modelo:id_modelo,nombre_modelo,id_marca',
            'ingreso.dispositivo.modelo.marca:id_marca,marca',
            'reparacionesMultiples',
            'reparacionesMultiples.pieza:id_pieza,nombre_pieza,precio',
        ])
            ->orderBy('id_reparacion', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $reparaciones
        ]);

    } catch (\Exception $e) {
        \Log::error('Error al cargar reparaciones del técnico', [
            'id_tecnico' => $idTecnico,
            'error'      => $e->getMessage()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Error al cargar reparaciones del técnico',
            'error'   => config('app.debug') ? $e->getMessage() : null
        ], 500);
    }
}
}