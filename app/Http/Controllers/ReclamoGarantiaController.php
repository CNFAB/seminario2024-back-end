<?php

namespace App\Http\Controllers;

use App\Models\ReclamoGarantia;
use App\Models\Garantia;
use App\Models\GarantiaPieza;
use App\Models\Reparacion;
use App\Models\Ingreso_d;
use App\Models\Dispositivo;
use App\Models\Usuario;
use App\Http\Requests\ReclamoGarantia\StoreReclamoGarantiaRequest;
use App\Http\Requests\ReclamoGarantia\UpdateReclamoGarantiaRequest;
use App\Http\Requests\ReclamoGarantia\UpdateReclamoGarantiaByClienteRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReclamoGarantiaController extends Controller
{

    /**
     * Obtener el usuario autenticado sin importar el guard
     */
    private function getAuthUser()
    {
        // Primero intentar guard de usuarios internos
        $user = auth('usuario')->user();
        if ($user) return $user;
        
        // Si no, intentar guard de clientes
        $user = auth('api')->user();
        if ($user) return $user;
        
        return null;
    }

    /**
     * Listar todos los reclamos (Admin/Técnico)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $this->getAuthUser();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autenticado'
                ], 401);
            }

            $query = ReclamoGarantia::with([
                'garantia.reparacion.ingreso.dispositivo.cliente',
                'garantia.reparacion.ingreso.dispositivo.modelo',
                'garantia.reparacion.diagnostico.ingreso.dispositivo.cliente',
                'garantia.reparacion.diagnostico.ingreso.dispositivo.modelo',
            ]);
            
            // Si es cliente, solo sus reclamos
            if ($user instanceof \App\Models\Cliente) {
                $query->whereHas('garantia', function($q) use ($user) {
                    $q->where('id_cliente', $user->id_cliente);
                });
            }
            
            $reclamos = $query->orderBy('created_at', 'desc')->get();
            
            return response()->json([
                'success' => true,
                'data' => $reclamos,
                'count' => $reclamos->count()
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cliente crea un nuevo reclamo
     */
    public function store(StoreReclamoGarantiaRequest $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $garantia = Garantia::findOrFail($request->id_garantia);

            if (!$garantia->isActiva()) {
                return response()->json([
                    'success' => false,
                    'message' => 'La garantía no está activa'
                ], 400);
            }

            // OBTENER EL TÉCNICO ORIGINAL
            $reparacionOriginal = $garantia->reparacion;
            $tecnicoOriginalId = $reparacionOriginal->id_usuario ?? null;
            
            // VERIFICAR SI EL TÉCNICO SIGUE ACTIVO
            $tecnicoAsignado = null;
            $tecnicoEstaActivo = false;
            
            if ($tecnicoOriginalId) {
                $tecnico = Usuario::find($tecnicoOriginalId);
                if ($tecnico && $tecnico->activo && $tecnico->es_tecnico) {
                    $tecnicoEstaActivo = true;
                    $tecnicoAsignado = $tecnicoOriginalId;
                }
            }
            
            // SI EL TÉCNICO NO ESTÁ ACTIVO, DEJAR NULL PARA QUE ADMIN LO ASIGNE
            if (!$tecnicoEstaActivo) {
                $tecnicoAsignado = null;
            }

            // Crear el reclamo con técnico asignado (o null)
            $reclamo = ReclamoGarantia::create([
                'id_garantia' => $garantia->id_garantia,
                'id_garantia_pieza' => $request->id_garantia_pieza,
                'fecha_reclamo' => now(),
                'descripcion_problema' => $request->descripcion_problema,
                'fotos' => $request->fotos ? json_encode($request->fotos) : null,
                'estado' => ReclamoGarantia::ESTADO_PENDIENTE,
                'prioridad' => ReclamoGarantia::PRIORIDAD_MEDIA,
                'monto_reclamado' => $request->monto_reclamado ?? 0,
                'id_tecnico_asignado' => $tecnicoAsignado
            ]);

            $garantia->estado = Garantia::ESTADO_RECLAMADA;
            $garantia->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Reclamo registrado correctamente',
                'data' => $reclamo->load(['garantia'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear reclamo', [
                'id_garantia' => $request->id_garantia,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar el reclamo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mostrar un reclamo específico
     */
    public function show($id): JsonResponse
    {
         \Log::info('=== show() llamado ===', [
        'id' => $id,
        'url' => request()->fullUrl(),
        'route_name' => request()->route()->getName(),
        'route_action' => request()->route()->getActionName(),
    ]);
        try {

            $reclamo = ReclamoGarantia::with([
                'garantia.reparacion.ingreso.dispositivo',
                'garantia.garantiaPiezas.pieza',
                'nuevaReparacion'
            ])->findOrFail($id);

            $user = $this->getAuthUser();

            if ($user instanceof \App\Models\Usuario) {
                return response()->json(['success' => true, 'data' => $reclamo]);
            }

            if ($user instanceof \App\Models\Cliente) {
                if ($reclamo->garantia?->id_cliente !== $user->id_cliente) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No autorizado'
                    ], 403);
                }
                return response()->json(['success' => true, 'data' => $reclamo]);
            }

            return response()->json(['success' => false, 'message' => 'No autenticado'], 401);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function update(UpdateReclamoGarantiaRequest $request, $id): JsonResponse
    {
        DB::beginTransaction();
        try {
            $reclamo = ReclamoGarantia::findOrFail($id);

            // Solo actualizar los campos que vienen en la request
            $reclamo->fill($request->only([
                'estado',
                'diagnostico_tecnico',
                'diagnostico_categoria',
                'comentario_tecnico',
                'prioridad',
                'monto_aprobado'
            ]));

            // Si el estado cambió a COMPLETADO, setear fecha_resolucion
            if ($request->has('estado') && $request->estado === ReclamoGarantia::ESTADO_COMPLETADO && !$reclamo->fecha_resolucion) {
                $reclamo->fecha_resolucion = now();
            }

            if ($request->has('estado') && $request->estado === ReclamoGarantia::ESTADO_APROBADO && !$reclamo->id_nueva_reparacion) {
            }

            $reclamo->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Reclamo actualizado correctamente',
                'data' => $reclamo->fresh(['garantia', 'nuevaReparacion'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al actualizar reclamo', [
                'id_reclamo' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el reclamo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cliente actualiza su reclamo (solo descripción y fotos)
     */
    public function updateByCliente(UpdateReclamoGarantiaByClienteRequest $request, $id): JsonResponse
    {
        DB::beginTransaction();
        try {
            $reclamo = ReclamoGarantia::findOrFail($id);

            $user = auth('api')->user();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'No autenticado'], 401);
            }
            $esPropietario = $reclamo->garantia?->id_cliente === $user->id_cliente;

            if (!$esPropietario) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autorizado'
                ], 403);
            }

            if ($reclamo->estado !== ReclamoGarantia::ESTADO_PENDIENTE) {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo se pueden editar reclamos pendientes'
                ], 400);
            }

            if ($request->has('descripcion_problema')) {
                $reclamo->descripcion_problema = $request->descripcion_problema;
            }

            if ($request->has('fotos')) {
                $reclamo->fotos = json_encode($request->fotos);
            }

            $reclamo->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Reclamo actualizado correctamente',
                'data' => $reclamo
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el reclamo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar un reclamo (solo admin)
     */
    public function destroy($id): JsonResponse
    {
        DB::beginTransaction();
        try {
            $reclamo = ReclamoGarantia::findOrFail($id);

            $user = $this->getAuthUser();
            if (!$user instanceof \App\Models\Usuario || !$user->es_administrador) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autorizado'
                ], 403);
            }

            $reclamo->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Reclamo eliminado correctamente'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el reclamo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener reclamos del cliente autenticado
     */
    public function misReclamos(): JsonResponse
    {
        try {
           $user = auth('cliente')->user();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'No autenticado'], 401);
            }
            
            $clienteId = $user->id_cliente;

            $dispositivosIds = Dispositivo::where('id_cliente', $clienteId)->pluck('id_dispositivo');
            $ingresosIds = Ingreso_d::whereIn('id_dispositivo', $dispositivosIds)->pluck('id_ingreso');
            $reparacionesDirectasIds = Reparacion::whereIn('id_ingreso', $ingresosIds)->pluck('id_reparacion');
            $reparacionesDesdeDiagnosticoIds = Reparacion::whereHas('diagnostico.ingreso', function($q) use ($dispositivosIds) {
                $q->whereIn('id_dispositivo', $dispositivosIds);
            })->pluck('id_reparacion');

            $reparacionesIds = $reparacionesDirectasIds->merge($reparacionesDesdeDiagnosticoIds)->unique();
            $garantiasIds = Garantia::whereIn('id_reparacion', $reparacionesIds)->pluck('id_garantia');

            $reclamos = ReclamoGarantia::whereIn('id_garantia', $garantiasIds)
                ->with([
                    'garantia.reparacion.ingreso.dispositivo.modelo',
                    'garantia.reparacion.diagnostico.ingreso.dispositivo.modelo',
                    'nuevaReparacion'
                ])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $reclamos,
                'message' => 'Tus reclamos obtenidos exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener tus reclamos',
                'error' => $e->getMessage()
            ], 500);
        }
    }
   public function misReclamosAsignados(): JsonResponse
{
    try {
        $user = auth('usuario')->user();
        
        if (!$user || !$user->es_tecnico) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado'
            ], 403);
        }
        
        $reclamos = ReclamoGarantia::where('id_tecnico_asignado', $user->id_usuario)
            ->with([
                'garantia.reparacion.ingreso.dispositivo.cliente',
                'garantia.reparacion.ingreso.dispositivo.modelo.marca',
                'garantia.reparacion.diagnostico.ingreso.dispositivo.cliente',
                'garantia.reparacion.diagnostico.ingreso.dispositivo.modelo.marca'
            ])
            ->orderBy('created_at', 'desc')
            ->get();
        
        // Log para depuración
        \Log::info('misReclamosAsignados - Reclamos encontrados:', [
            'count' => $reclamos->count(),
            'primer_reclamo' => $reclamos->first() ? [
                'id' => $reclamos->first()->id_reclamo,
                'garantia_id' => $reclamos->first()->id_garantia,
                'tiene_garantia' => $reclamos->first()->garantia ? 'SI' : 'NO',
                'tiene_reparacion' => $reclamos->first()->garantia?->reparacion ? 'SI' : 'NO',
                'tiene_ingreso' => $reclamos->first()->garantia?->reparacion?->ingreso ? 'SI' : 'NO',
                'tiene_dispositivo' => $reclamos->first()->garantia?->reparacion?->ingreso?->dispositivo ? 'SI' : 'NO',
            ] : null
        ]);
        
        return response()->json([
            'success' => true,
            'data' => $reclamos,
            'count' => $reclamos->count()
        ]);
        
    } catch (\Exception $e) {
        \Log::error('Error en misReclamosAsignados:', [
            'error' => $e->getMessage(),
            'line' => $e->getLine()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ], 500);
    }
}
    /**
     * Obtener reclamos por garantía
     */
    public function porGarantia($idGarantia): JsonResponse
    {
        try {
            $reclamos = ReclamoGarantia::where('id_garantia', $idGarantia)
                ->with(['nuevaReparacion'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $reclamos,
                'count' => $reclamos->count(),
                'message' => 'Reclamos de la garantía obtenidos exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los reclamos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Registrar ingreso físico del dispositivo cuando el cliente llega al taller
     */
public function registrarIngreso(Request $request, $id): JsonResponse
{
    DB::beginTransaction();
    try {
        $recepcionistaId = auth('usuario')->id();

        if (!$recepcionistaId) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo identificar el usuario que registra el ingreso'
            ], 400);
        }
        
        $reclamo = ReclamoGarantia::with([
            'garantia.reparacion.diagnostico.ingreso.dispositivo',
            'garantia.reparacion.ingreso.dispositivo',
            'garantia.garantiaPiezas.pieza.categoria'
        ])->findOrFail($id);
        
        // Verificar que el reclamo esté aprobado
        if ($reclamo->estado !== ReclamoGarantia::ESTADO_APROBADO) {
            return response()->json([
                'success' => false,
                'message' => 'El reclamo debe estar APROBADO para registrar el ingreso'
            ], 400);
        }
        
        // Verificar que no tenga ya una reparación
        if ($reclamo->id_nueva_reparacion) {
            return response()->json([
                'success' => false,
                'message' => 'Este reclamo ya tiene una reparación asociada'
            ], 400);
        }
        
        // Obtener dispositivo
        $garantia = $reclamo->garantia;
        $dispositivo = null;
        
        if ($garantia->dispositivo) {
            $dispositivo = $garantia->dispositivo;
        } elseif ($garantia->reparacion?->ingreso?->dispositivo) {
            $dispositivo = $garantia->reparacion->ingreso->dispositivo;
        } elseif ($garantia->reparacion?->diagnostico?->ingreso?->dispositivo) {
            $dispositivo = $garantia->reparacion->diagnostico->ingreso->dispositivo;
        }
        
        if (!$dispositivo) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo identificar el dispositivo'
            ], 500);
        }
        
        $tecnicoAsignado = $reclamo->id_tecnico_asignado ?? null;
        
        if (!$tecnicoAsignado) {
            return response()->json([
                'success' => false,
                'message' => 'El reclamo no tiene un técnico asignado'
            ], 400);
        }
        
        // ============================================
        // Obtener las categorías de las piezas cubiertas
        // ============================================
        $categoriasCubiertas = [];
        
        if ($reclamo->garantia && $reclamo->garantia->garantiaPiezas) {
            foreach ($reclamo->garantia->garantiaPiezas as $garantiaPieza) {
                if ($garantiaPieza->pieza && $garantiaPieza->pieza->categoria) {
                    $categoria = $garantiaPieza->pieza->categoria->categoria;
                    if (!in_array($categoria, $categoriasCubiertas)) {
                        $categoriasCubiertas[] = $categoria;
                    }
                }
            }
        }
        
        // Crear texto de categorías para el comentario
        $categoriasTexto = '';
        if (count($categoriasCubiertas) > 0) {
            $categoriasTexto = ' (Piezas cubiertas: ' . implode(', ', $categoriasCubiertas) . ')';
        }
        
        // ============================================
        // Obtener valores de memoria_sd y sim
        // ============================================
        $memoriaSd = $request->input('memoria_sd');
        $sim = $request->input('sim');
        
        if (is_string($memoriaSd)) {
            $memoriaSd = filter_var($memoriaSd, FILTER_VALIDATE_BOOLEAN);
        }
        if (is_string($sim)) {
            $sim = filter_var($sim, FILTER_VALIDATE_BOOLEAN);
        }
        
        $memoriaSd = $memoriaSd ?? false;
        $sim = $sim ?? false;
        
        // ============================================
        // Crear el ingreso
        // ============================================
        $ingreso = Ingreso_d::create([
            'id_dispositivo' => $dispositivo->id_dispositivo,
            'id_usuario' => $recepcionistaId,
            'memoria_sd' => $memoriaSd,
            'sim' => $sim,
            'estado_del_ingreso' => 'RE-INGRESO POR GARANTIA',
            'comentario_cliente' => 'cubre la garantia',
            'fecha_ingreso' => now(),
            'observaciones' => 'Reparación por garantía - Reclamo #' . $reclamo->id_reclamo . ' | Categorías: ' . implode(', ', $categoriasCubiertas),
            'revision_tecnica' => false
        ]);
        
        // ============================================
        // Crear reparación con comentario ENRIQUECIDO
        // ============================================
        $reparacion = Reparacion::create([
            'id_ingreso' => $ingreso->id_ingreso,
            'id_usuario' => $tecnicoAsignado,
            'comentario' => 'Reparación cubierta por garantía - Reclamo #'  . $categoriasTexto,
            'estado' => 'PENDIENTE',
            'costo_total' => 0,
            'es_garantia' => true,
            'estado_pago' => 'PAGADO',
            'fecha_pago' => now()
        ]);
        
        // Vincular reclamo con la nueva reparación
        $reclamo->id_nueva_reparacion = $reparacion->id_reparacion;
        $reclamo->save();
        
        DB::commit();
        
        return response()->json([
            'success' => true,
            'message' => 'Ingreso registrado correctamente. El dispositivo está en taller.',
            'data' => [
                'reclamo' => $reclamo,
                'ingreso' => $ingreso,
                'reparacion' => $reparacion,
                'categorias_cubiertas' => $categoriasCubiertas
            ]
        ]);
        
    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Error al registrar ingreso de garantía', [
            'id_reclamo' => $id,
            'error' => $e->getMessage(),
            'line' => $e->getLine()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Error al registrar el ingreso: ' . $e->getMessage()
        ], 500);
    }
}
  public function reclamosAprobadosPorCliente($email): JsonResponse
{
    try {
        // Buscar cliente por correo
        $cliente = \App\Models\Cliente::where('correo', $email)->first();
        
        if (!$cliente) {
            return response()->json([
                'success' => false,
                'message' => 'Cliente no encontrado'
            ], 404);
        }
        
        // Obtener dispositivos del cliente
        $dispositivosIds = Dispositivo::where('id_cliente', $cliente->id_cliente)->pluck('id_dispositivo');
        
        if ($dispositivosIds->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'El cliente no tiene dispositivos registrados'
            ], 404);
        }
        
        // Obtener ingresos de esos dispositivos
        $ingresosIds = Ingreso_d::whereIn('id_dispositivo', $dispositivosIds)->pluck('id_ingreso');
        
        if ($ingresosIds->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Los dispositivos no tienen ingresos registrados'
            ], 404);
        }
        
        // Obtener reparaciones (directas y por diagnóstico)
        $reparacionesDirectasIds = Reparacion::whereIn('id_ingreso', $ingresosIds)->pluck('id_reparacion');
        $reparacionesDiagnosticoIds = Reparacion::whereHas('diagnostico.ingreso', function($q) use ($dispositivosIds) {
            $q->whereIn('id_dispositivo', $dispositivosIds);
        })->pluck('id_reparacion');
        
        $reparacionesIds = $reparacionesDirectasIds->merge($reparacionesDiagnosticoIds)->unique();
        
        if ($reparacionesIds->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No hay reparaciones asociadas a los ingresos'
            ], 404);
        }
        
        // Obtener garantías de esas reparaciones
        $garantiasIds = Garantia::whereIn('id_reparacion', $reparacionesIds)->pluck('id_garantia');
        
        if ($garantiasIds->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Las reparaciones no tienen garantías asociadas'
            ], 404);
        }
        
        // ✅ Obtener reclamos con todas las relaciones necesarias
        $reclamos = ReclamoGarantia::whereIn('id_garantia', $garantiasIds)
            ->with([
                'garantia.reparacion.ingreso.dispositivo.modelo.marca',
                'garantia.reparacion.ingreso.dispositivo.cliente',
                'garantia.reparacion.diagnostico.ingreso.dispositivo.modelo.marca',
                'garantia.reparacion.diagnostico.ingreso.dispositivo.cliente'
            ])
            ->orderBy('created_at', 'desc')
            ->get();
        
        return response()->json([
            'success' => true,
            'data' => $reclamos,
            'cliente' => $cliente,
            'resumen' => [
                'total' => $reclamos->count(),
                'pendientes' => $reclamos->where('estado', ReclamoGarantia::ESTADO_PENDIENTE)->count(),
                'en_revision' => $reclamos->where('estado', ReclamoGarantia::ESTADO_EN_REVISION)->count(),
                'aprobados' => $reclamos->where('estado', ReclamoGarantia::ESTADO_APROBADO)->count(),
                'rechazados' => $reclamos->where('estado', ReclamoGarantia::ESTADO_RECHAZADO)->count(),
                'completados' => $reclamos->where('estado', ReclamoGarantia::ESTADO_COMPLETADO)->count(),
                'pendientes_ingreso' => $reclamos->where('estado', ReclamoGarantia::ESTADO_APROBADO)
                                            ->whereNull('id_nueva_reparacion')
                                            ->count()
            ]
        ]);
        
    } catch (\Exception $e) {
        \Log::error('Error reclamosAprobadosPorCliente:', [
            'email' => $email,
            'error' => $e->getMessage(),
            'line' => $e->getLine()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ], 500);
    }
}
}