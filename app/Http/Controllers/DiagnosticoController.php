<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Diagnostico;
use App\Models\Usuario;
use App\Models\Ingreso_d;
use App\Models\Reparacion;
use App\Models\Pieza;
use App\Models\ReparacionMultiple;
use App\Http\Requests\Diagnostico\StoreDiagnosticoRequest;
use App\Http\Requests\Diagnostico\UpdateDiagnosticoRequest;
use App\Http\Requests\Diagnostico\CrearAutomaticoRequest;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class DiagnosticoController extends Controller
{
    // ============================================
    // CRUD BÁSICO
    // ============================================

    public function index(Request $request): JsonResponse
    {
        try {
            $query = Diagnostico::query();

            if ($request->has('estado'))     $query->where('estado',     $request->estado);
            if ($request->has('gravedad'))   $query->where('gravedad',   $request->gravedad);
            if ($request->has('id_usuario')) $query->where('id_usuario', $request->id_usuario);
            if ($request->has('id_ingreso')) $query->where('id_ingreso', $request->id_ingreso);
            if ($request->has('id_pieza'))   $query->where('id_pieza',   $request->id_pieza);

            $query->orderBy(
                $request->get('sort_by', 'fecha_expiracion'),
                $request->get('sort_order', 'asc')
            );

            $diagnosticos = $query->with([
                 'ingreso.dispositivo.modelo.marca',  
                'usuario',
                'precioReparacion',
                'pieza',
                'reparacion'
            ])->get();

            return response()->json(['success' => true, 'data' => $diagnosticos]);

        } catch (\Exception $e) {
            \Log::error('Error en index de diagnósticos', ['error' => $e->getMessage()]);
            return $this->errorResponse('Error al obtener los diagnósticos', $e);
        }
    }

    public function store(StoreDiagnosticoRequest $request): JsonResponse
    {
        try {
            $diagnostico = Diagnostico::create($request->validated());

            \Log::info('Diagnóstico creado', [
                'id_diagnostico' => $diagnostico->id_diagnostico,
                'id_pieza'       => $diagnostico->id_pieza,
                'id_ingreso'     => $diagnostico->id_ingreso,
                'id_usuario'     => $diagnostico->id_usuario,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Diagnóstico creado exitosamente',
                'data'    => $diagnostico->load(['ingreso', 'usuario', 'precioReparacion', 'pieza'])
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Error al crear diagnóstico', ['error' => $e->getMessage(), 'data' => $request->all()]);
            return $this->errorResponse('Error al crear el diagnóstico', $e);
        }
    }

    public function show( $id): JsonResponse
    {
        try {
            $diagnostico = Diagnostico::with([
                'ingreso.dispositivo.modelo.marca',
                'ingreso.cliente',
                'usuario',
                'precioReparacion.categoria',
                'reparacion',
                'pieza'
            ])->findOrFail($id);

            return response()->json(['success' => true, 'data' => $diagnostico]);

        } catch (ModelNotFoundException $e) {
            return $this->notFoundResponse('Diagnóstico no encontrado');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener el diagnóstico', $e);
        }
    }

    public function update(UpdateDiagnosticoRequest $request, $id): JsonResponse
    {
        try {
            $cleanId = preg_replace('/[^0-9]/', '', $id);

            if (empty($cleanId)) {
                return response()->json(['success' => false, 'message' => 'ID inválido'], 400);
            }

      $diagnostico = Diagnostico::with('ingreso.dispositivo.cliente', 'ingreso.dispositivo.modelo.marca')->findOrFail($cleanId);
            $datosValidados  = $request->validated();
            $idPiezaAnterior = $diagnostico->id_pieza;

            if (empty($datosValidados)) {
                return response()->json(['success' => false, 'message' => 'No se enviaron datos para actualizar'], 400);
            }
            // ============================================
            // GUARDAR ESTADO ANTERIOR ANTES DE ACTUALIZAR
            // ============================================
            $estadoAnterior = $diagnostico->estado;
            // ============================================

            $diagnostico->fill($datosValidados);

            if (!$diagnostico->isDirty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No se detectaron cambios en los datos',
                    'data'    => $diagnostico->load(['ingreso', 'usuario', 'precioReparacion', 'pieza'])
                ]);
            }

            $camposModificados = $diagnostico->getDirty();

            if (isset($camposModificados['id_pieza'])) {
                \Log::info('Cambio de pieza en diagnóstico', [
                    'id_diagnostico'    => $diagnostico->id_diagnostico,
                    'id_pieza_anterior' => $idPiezaAnterior,
                    'id_pieza_nuevo'    => $diagnostico->id_pieza,
                ]);
            }

            \Log::info('Actualizando diagnóstico', [
                'id'                 => $cleanId,
                'campos_modificados' => array_keys($camposModificados),
                'valores_anteriores' => collect($camposModificados)->mapWithKeys(
                    fn($value, $field) => [$field => $diagnostico->getOriginal($field)]
                ),
                'valores_nuevos'     => $camposModificados,
            ]);

            $diagnostico->save();
             // ============================================
        // ENVIAR NOTIFICACIÓN SI CAMBIÓ EL ESTADO
        // ============================================
        if (isset($camposModificados['estado']) && $estadoAnterior !== $diagnostico->estado) {
            $cliente = $diagnostico->ingreso?->dispositivo?->cliente;
            
            if ($cliente) {
                try {
                    $cliente->notify(new \App\Notifications\DiagnosticoEstadoChanged(
                        $diagnostico,
                        $estadoAnterior,
                        $diagnostico->estado
                    ));
                    
                    \Log::info('✅ Notificación enviada desde update', [
                        'id_diagnostico' => $diagnostico->id_diagnostico,
                        'cliente_email' => $cliente->correo,
                        'estado_anterior' => $estadoAnterior,
                        'estado_nuevo' => $diagnostico->estado
                    ]);
                } catch (\Exception $e) {
                    \Log::error('❌ Error al enviar notificación desde update', [
                        'id_diagnostico' => $diagnostico->id_diagnostico,
                        'error' => $e->getMessage()
                    ]);
                }
            } else {
                \Log::info('ℹ️ No se envió notificación desde update - cliente no encontrado', [
                    'id_diagnostico' => $diagnostico->id_diagnostico
                ]);
            }
        }
        // ============================================
            $diagnostico->load(['ingreso', 'usuario', 'precioReparacion', 'pieza']);

            return response()->json([
                'success'             => true,
                'message'             => $this->generarMensajeActualizacion($camposModificados),
                'data'                => $diagnostico,
                'campos_actualizados' => array_keys($camposModificados)
            ]);

        } catch (ModelNotFoundException $e) {
            return $this->notFoundResponse('Diagnóstico no encontrado');
        } catch (\Exception $e) {
            \Log::error('Error al actualizar diagnóstico', ['id' => $id, 'error' => $e->getMessage()]);
            return $this->errorResponse('Error al actualizar el diagnóstico', $e);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $diagnostico = Diagnostico::findOrFail($id);

            if ($diagnostico->reparacion) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar el diagnóstico porque tiene una reparación asociada'
                ], 400);
            }

            \Log::info('Eliminando diagnóstico', [
                'id_diagnostico' => $diagnostico->id_diagnostico,
                'id_pieza'       => $diagnostico->id_pieza,
                'id_ingreso'     => $diagnostico->id_ingreso,
            ]);

            $diagnostico->delete();

            return response()->json(['success' => true, 'message' => 'Diagnóstico eliminado exitosamente']);

        } catch (ModelNotFoundException $e) {
            return $this->notFoundResponse('Diagnóstico no encontrado');
        } catch (\Exception $e) {
            return $this->errorResponse('Error al eliminar el diagnóstico', $e);
        }
    }

    // ============================================
    // CAMBIAR ESTADO
    // ============================================

public function cambiarEstado(Request $request, int $id): JsonResponse
{
    $request->validate([
        'estado' => ['required', 'string', 'in:ESPERANDO_APROBACION,EN_REVISION,EN_REPARACION,LISTO_PARA_RETIRAR,APROBADO,RECHAZADO,NO_REPARADO,EXPIRADO']
    ]);

    try {
        $diagnostico = Diagnostico::with('ingreso.dispositivo.cliente', 'ingreso.dispositivo.modelo.marca')
            ->findOrFail($id);

        $fechaExpiracion = $diagnostico->fecha_expiracion;
        $yaExpiro = $fechaExpiracion && now()->greaterThan($fechaExpiracion);

        // ✅ Registrar inicio de revisión (cuando pasa a EN_REVISION)
        if ($request->estado === 'EN_REVISION' && !$diagnostico->fecha_inicio_revision) {
            $diagnostico->fecha_inicio_revision = now();
        }

        // ✅ Registrar fin de revisión (cuando se completa)
        if (in_array($request->estado, ['LISTO_PARA_RETIRAR', 'APROBADO']) && !$diagnostico->fecha_fin_revision) {
            $diagnostico->fecha_fin_revision = now();
        }

        // ✅ Permitir siempre el cambio a EXPIRADO si realmente expiró (para cron y lazy update)
        if ($request->estado === 'EXPIRADO') {
            if (!$yaExpiro) {
                return response()->json([
                    'success' => false,
                    'message' => 'El diagnóstico aún no ha expirado, no se puede marcar como EXPIRADO.'
                ], 400);
            }

            $estadoAnterior = $diagnostico->estado;
            $diagnostico->estado = 'EXPIRADO';
            $diagnostico->save();

            // 👉 También marcar piezas como expiradas
            \DB::table('diagnostico_pieza')
                ->where('id_diagnostico', $diagnostico->id_diagnostico)
                ->whereIn('estado', ['PENDIENTE', 'ESPERANDO_APROBACION'])
                ->update([
                    'estado' => 'EXPIRADO',
                    'fecha_rechazo' => now()
                ]);

            \Log::info('Diagnóstico marcado como EXPIRADO', [
                'id_diagnostico'  => $diagnostico->id_diagnostico,
                'estado_anterior' => $estadoAnterior,
                'fecha_expiracion'=> $fechaExpiracion,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Diagnóstico marcado como EXPIRADO correctamente.',
                'data'    => $diagnostico
            ]);
        }

        // ✅ Si ya expiró e intenta cambiarlo a cualquier otro estado
        if ($yaExpiro) {
            // Aprovechar para corregir el estado en BD si todavía figura como ESPERANDO_APROBACION
            if ($diagnostico->estado === 'ESPERANDO_APROBACION') {
                $diagnostico->estado = 'EXPIRADO';
                $diagnostico->save();

                // 👉 También marcar piezas como expiradas
                \DB::table('diagnostico_pieza')
                    ->where('id_diagnostico', $diagnostico->id_diagnostico)
                    ->whereIn('estado', ['PENDIENTE', 'ESPERANDO_APROBACION'])
                    ->update([
                        'estado' => 'EXPIRADO',
                        'fecha_rechazo' => now()
                    ]);

                \Log::info('Diagnóstico expirado actualizado automáticamente a EXPIRADO', [
                    'id_diagnostico'  => $diagnostico->id_diagnostico,
                    'fecha_expiracion'=> $fechaExpiracion,
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Este diagnóstico expiró el ' . \Carbon\Carbon::parse($fechaExpiracion)->format('d/m/Y') . '. No se puede modificar.',
                'code'    => 'DIAGNOSTICO_EXPIRADO',
                'data'    => $diagnostico
            ], 400);
        }

        // ✅ Bloquear cambios si ya está EXPIRADO en BD
        if ($diagnostico->estado === 'EXPIRADO') {
            return response()->json([
                'success' => false,
                'message' => 'Este diagnóstico ya está expirado, no se puede cambiar su estado.'
            ], 400);
        }

        // ── Cambio de estado normal ──────────────────────────────────────────
        $estadoAnterior      = $diagnostico->estado;
        $diagnostico->estado = $request->estado;
        $diagnostico->save();

        // 👉 NUEVO: Si se rechazó, marcar todas las piezas como RECHAZADO ─────
        if ($request->estado === 'RECHAZADO') {
            \DB::table('diagnostico_pieza')
                ->where('id_diagnostico', $diagnostico->id_diagnostico)
                ->whereIn('estado', ['PENDIENTE', 'ESPERANDO_APROBACION'])
                ->update([
                    'estado' => 'RECHAZADO',
                    'fecha_rechazo' => now()
                ]);

            \Log::info('Piezas rechazadas por rechazo del diagnóstico', [
                'id_diagnostico' => $diagnostico->id_diagnostico
            ]);
        }

        \Log::info('Cambio de estado en diagnóstico', [
            'id_diagnostico'  => $diagnostico->id_diagnostico,
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo'    => $request->estado,
            'id_pieza'        => $diagnostico->id_pieza,
            'fecha_inicio_revision' => $diagnostico->fecha_inicio_revision,
            'fecha_fin_revision' => $diagnostico->fecha_fin_revision,
        ]);

        // ── Notificación por correo ──────────────────────────────────────────
        $cliente = $diagnostico->ingreso?->dispositivo?->cliente;

        if ($cliente && $estadoAnterior !== $request->estado) {
            try {
                $cliente->notify(new \App\Notifications\DiagnosticoEstadoChanged(
                    $diagnostico,
                    $estadoAnterior,
                    $request->estado
                ));

                \Log::info('Notificación enviada', [
                    'id_diagnostico' => $diagnostico->id_diagnostico,
                    'cliente_email'  => $cliente->correo,
                    'estado_nuevo'   => $request->estado
                ]);
            } catch (\Exception $e) {
                \Log::error('Error al enviar notificación', [
                    'id_diagnostico' => $diagnostico->id_diagnostico,
                    'error'          => $e->getMessage()
                ]);
            }
        }

        // ── Si se aprobó, crear la reparación ───────────────────────────────
        if ($request->estado === 'APROBADO') {
            $this->crearReparacionDesdeAprobacion($diagnostico);
        }

        return response()->json([
            'success' => true,
            'message' => 'Estado actualizado exitosamente',
            'data'    => $diagnostico->load(['usuario', 'ingreso', 'pieza'])
        ]);

    } catch (ModelNotFoundException $e) {
        return $this->notFoundResponse('Diagnóstico no encontrado');
    } catch (\Exception $e) {
        \Log::error('Error al cambiar estado', ['id' => $id, 'error' => $e->getMessage()]);
        return $this->errorResponse('Error al cambiar el estado', $e);
    }
}
    /**
 * Aprobar una pieza específica de un diagnóstico
 * PATCH /api/diagnosticos/{idDiagnostico}/piezas/{idPieza}/aprobar
 */
public function aprobarPiezaDiagnostico($idDiagnostico, $idPieza)
{
    try {
        // Buscar el registro en la tabla pivote diagnostico_pieza
        $diagnosticoPieza = DiagnosticoPieza::where('id_diagnostico', $idDiagnostico)
            ->where('id_pieza', $idPieza)
            ->first();
        
        if (!$diagnosticoPieza) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró la relación entre diagnóstico y pieza'
            ], 404);
        }
        
        // Actualizar el estado de la pieza en este diagnóstico
        $diagnosticoPieza->update([
            'estado' => 'APROBADO'
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Pieza aprobada correctamente',
            'data' => $diagnosticoPieza
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error al aprobar la pieza: ' . $e->getMessage()
        ], 500);
    }
}

/**
 * Rechazar una pieza específica de un diagnóstico
 * PATCH /api/diagnosticos/{idDiagnostico}/piezas/{idPieza}/rechazar
 */
public function rechazarPiezaDiagnostico($idDiagnostico, $idPieza)
{
    try {
        $diagnosticoPieza = DiagnosticoPieza::where('id_diagnostico', $idDiagnostico)
            ->where('id_pieza', $idPieza)
            ->first();
        
        if (!$diagnosticoPieza) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró la relación entre diagnóstico y pieza'
            ], 404);
        }
        
        $diagnosticoPieza->update([
            'estado' => 'RECHAZADO'
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Pieza rechazada correctamente',
            'data' => $diagnosticoPieza
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error al rechazar la pieza: ' . $e->getMessage()
        ], 500);
    }
}

    // ============================================
    // CONSULTAS ESPECÍFICAS
    // ============================================

    public function porTecnico($idTecnico): JsonResponse
    {
        try {
            $diagnosticos = Diagnostico::where('id_usuario', $idTecnico)
                ->with(['ingreso.dispositivo.modelo.marca', 'usuario', 'precioReparacion', 'pieza'])
                ->orderBy('id_diagnostico', 'desc')
                ->get();

            return response()->json(['success' => true, 'data' => $diagnosticos]);

        } catch (\Exception $e) {
            \Log::error('Error al cargar diagnósticos del técnico', ['id_tecnico' => $idTecnico, 'error' => $e->getMessage()]);
            return $this->errorResponse('Error al cargar diagnósticos del técnico', $e);
        }
    }

    public function porIngreso(int $ingresoId): JsonResponse
    {
        try {
            $diagnosticos = Diagnostico::where('id_ingreso', $ingresoId)
                ->with(['usuario', 'precioReparacion', 'pieza'])
                ->orderBy('id_diagnostico', 'desc')
                ->get();

            return response()->json(['success' => true, 'data' => $diagnosticos]);

        } catch (\Exception $e) {
            \Log::error('Error al obtener diagnósticos por ingreso', ['id_ingreso' => $ingresoId, 'error' => $e->getMessage()]);
            return $this->errorResponse('Error al obtener diagnósticos por ingreso', $e);
        }
    }

    public function porUsuario(int $usuarioId): JsonResponse
    {
        try {
            $diagnosticos = Diagnostico::where('id_usuario', $usuarioId)
                ->with(['ingreso', 'precioReparacion', 'pieza'])
                ->orderBy('id_diagnostico', 'desc')
                ->paginate(15);

            return response()->json(['success' => true, 'data' => $diagnosticos]);

        } catch (\Exception $e) {
            \Log::error('Error al obtener diagnósticos por usuario', ['id_usuario' => $usuarioId, 'error' => $e->getMessage()]);
            return $this->errorResponse('Error al obtener diagnósticos por usuario', $e);
        }
    }
public function porDispositivoCliente($idDispositivo)
{
    try {
        $diagnosticos = Diagnostico::whereHas('ingreso', function($query) use ($idDispositivo) {
            $query->where('id_dispositivo', $idDispositivo);
        })
        ->with([
            'ingreso.dispositivo.modelo.marca',
            'ingreso.cliente',
            'usuario',
            'reparacion.reparacionesMultiples.pieza',
            'piezas'
        ])
        ->get()
        ->map(function($diagnostico) {
            $data = $diagnostico->toArray();
            
            if ($diagnostico->reparacion) {
                $data['reparacion'] = [
                    'id_reparacion' => $diagnostico->reparacion->id_reparacion,
                    'estado_general' => $diagnostico->reparacion->estado_general,
                     'estado_pago'            => $diagnostico->reparacion->estado_pago,
                    'reparaciones_multiples' => $diagnostico->reparacion->reparacionesMultiples->map(function($rep) {
                        return [
                            'id_multiple' => $rep->id_multiple,
                            'id_pieza' => $rep->id_pieza,
                            'estado' => $rep->estado,
                             'estado_pago'          => $rep->estado_pago,
                            'precio_total' => $rep->precio_total,
                            'precio_pieza_momento' => $rep->precio_pieza_momento,
                            'mano_obra_momento' => $rep->mano_obra_momento,
                            'comentario_tecnico' => $rep->comentario_tecnico,
                            'pieza' => $rep->pieza ? [
                                'id_pieza' => $rep->pieza->id_pieza,
                                'nombre_pieza' => $rep->pieza->nombre_pieza,
                                'precio' => $rep->pieza->precio,
                            ] : null
                        ];
                    })
                ];
            }
            
            if ($diagnostico->piezas) {
                $data['piezas'] = $diagnostico->piezas->map(function($pieza) {
                    return [
                        'id_diagnostico_pieza' => $pieza->pivot->id_diagnostico_pieza,
                        'id_pieza' => $pieza->id_pieza,
                        'nombre_pieza' => $pieza->nombre_pieza,
                        'costo' => $pieza->pivot->costo,
                        'estado' => strtoupper($pieza->pivot->estado),
                        'comentario' => $pieza->pivot->comentario,
                    ];
                });
            }
            
            return $data;
        });
        
        return response()->json([
            'success' => true,
            'data' => $diagnosticos
        ]);
        
    } catch (\Exception $e) {
        \Log::error('Error en porDispositivoCliente', [
            'id_dispositivo' => $idDispositivo,
            'error' => $e->getMessage()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Error al obtener diagnósticos',
            'error' => config('app.debug') ? $e->getMessage() : null
        ], 500);
    }
}

    // ============================================
    // DIAGNÓSTICO AUTOMÁTICO
    // ============================================

   public function crearAutomatico(CrearAutomaticoRequest $request): JsonResponse
{
    try {
        $ingreso = Ingreso_d::with(['cliente', 'dispositivo'])->findOrFail($request->id_ingreso);

        if (Diagnostico::where('id_ingreso', $request->id_ingreso)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Este ingreso ya tiene un diagnóstico asociado'
            ], 400);
        }

        // ── elegir técnico ────────────────────────────────────────────
        if ($request->filled('id_usuario')) {
            // El recepcionista eligió un técnico específico desde el formulario
            $tecnico = Usuario::where('id_usuario', $request->id_usuario)
                ->where('es_tecnico', true)
                ->first();

            if (!$tecnico) {
                return response()->json([
                    'success' => false,
                    'message' => 'El técnico seleccionado no existe o no tiene rol de técnico'
                ], 400);
            }

            // Cargar el count manualmente para devolverlo en la respuesta
            $tecnico->loadCount(['diagnosticos' => fn($q) => $q->whereIn('estado', ['ESPERANDO_DIAGNOSTICO', 'EN_REPARACION'])]);

        } else {
            // Fallback: asignar automáticamente al de menor carga
            $tecnico = Usuario::where('es_tecnico', true)
                ->where('activo', 1)
                ->withCount(['diagnosticos' => fn($q) => $q->whereIn('estado', ['ESPERANDO_DIAGNOSTICO', 'EN_REPARACION'])])
                ->orderBy('diagnosticos_count', 'asc')
                ->first();

            if (!$tecnico) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay técnicos disponibles en este momento'
                ], 404);
            }
        }
        // ─────────────────────────────────────────────────────────────

        if ($request->filled('id_pieza') && !Pieza::find($request->id_pieza)) {
            return response()->json(['success' => false, 'message' => 'La pieza seleccionada no existe'], 400);
        }

        $clienteInfo = $ingreso->cliente
            ? 'Cliente: ' . trim($ingreso->cliente->nombre . ' ' . $ingreso->cliente->apellido)
            : '';

        $diagnostico = Diagnostico::create([
            'id_ingreso'       => $request->id_ingreso,
            'id_usuario'       => $tecnico->id_usuario,  // ← técnico elegido o fallback
            'id_pieza'         => $request->id_pieza ?? null,
            'observacion'      => $request->observacion ?? 'Diagnóstico automático creado tras ingreso del dispositivo. ' . $clienteInfo,
            'costo'            => 0.00,
            'costo_reparacion' => 0.00,
            'estado'           => 'ESPERANDO_DIAGNOSTICO',
            'gravedad'         => 'LEVE',
            'causa_detectada'  => 'Por determinar',
            'solucion'         => 'Por determinar',
            'fecha_expiracion' => $request->fecha_expiracion ?? now()->addDays(7)->toDateString(),
            'id_precio_r'      => null,
        ]);

        \Log::info('Diagnóstico automático creado', [
            'id_diagnostico'   => $diagnostico->id_diagnostico,
            'id_ingreso'       => $diagnostico->id_ingreso,
            'id_tecnico'       => $tecnico->id_usuario,
            'tecnico_manual'   => $request->filled('id_usuario'), // true = eligió el recepcionista
            'id_pieza'         => $diagnostico->id_pieza,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Diagnóstico creado automáticamente',
            'data'    => [
                'diagnostico'      => $diagnostico->load(['usuario', 'ingreso.cliente', 'pieza']),
                'tecnico_asignado' => [
                    'id'              => $tecnico->id_usuario,
                    'nombre_completo' => trim($tecnico->nombre . ' ' . $tecnico->apellido),
                    'carga_actual'    => $tecnico->diagnosticos_count,
                    'email'           => $tecnico->email,
                ],
                'cliente_info' => $ingreso->cliente ? [
                    'id'              => $ingreso->cliente->id_cliente,
                    'nombre_completo' => trim($ingreso->cliente->nombre . ' ' . $ingreso->cliente->apellido),
                ] : null,
                'pieza_info' => $diagnostico->pieza ? [
                    'id'     => $diagnostico->pieza->id_pieza,
                    'nombre' => $diagnostico->pieza->nombre_pieza,
                    'precio' => $diagnostico->pieza->precio,
                ] : null,
            ]
        ], 201);

    } catch (ModelNotFoundException $e) {
        return $this->notFoundResponse('Ingreso no encontrado');
    } catch (\Exception $e) {
        \Log::error('Error al crear diagnóstico automático', [
            'error'   => $e->getMessage(),
            'request' => $request->all()
        ]);
        return $this->errorResponse('Error al crear diagnóstico automático', $e);
    }
}

    // ============================================
    // MIS PENDIENTES (TÉCNICO AUTENTICADO)
    // ============================================

    public function misPendientes(Request $request): JsonResponse
    {
        try {
            $tecnicoId = auth()->id();

            if (!$tecnicoId) {
                return response()->json(['success' => false, 'message' => 'Usuario no autenticado'], 401);
            }

            $usuario = Usuario::find($tecnicoId);
            if (!$usuario || $usuario->es_tecnico != 1) {
                return response()->json(['success' => false, 'message' => 'El usuario no tiene permisos de técnico'], 403);
            }

            $query = Diagnostico::where('id_usuario', $tecnicoId)
                ->whereIn('estado', ['ESPERANDO_DIAGNOSTICO', 'EN_REVISION', 'APROBADO'])
                ->with([
                    'ingreso.dispositivo.modelo.marca',
                    'ingreso.dispositivo.cliente',
                    'precioReparacion.categoria',
                    'pieza'
                ]);

            if ($request->has('estado'))   $query->where('estado',   $request->estado);
            if ($request->has('gravedad')) $query->where('gravedad', $request->gravedad);
            if ($request->has('id_pieza')) $query->where('id_pieza', $request->id_pieza);

            $allowed   = ['id_diagnostico', 'fecha_expiracion', 'gravedad', 'estado', 'created_at'];
            $sortBy    = $request->get('sort_by', 'fecha_expiracion');
            $sortOrder = $request->get('sort_order', 'asc');
            $query->orderBy(in_array($sortBy, $allowed) ? $sortBy : 'fecha_expiracion', $sortOrder);

            $diagnosticos = $request->get('paginate') === 'false'
                ? $query->get()
                : $query->paginate($request->get('per_page', 20));

            return response()->json([
                'success'          => true,
                'data'             => $diagnosticos,
                'tecnico'          => [
                    'id'       => $usuario->id_usuario,
                    'nombre'   => $usuario->nombre,
                    'apellido' => $usuario->apellido,
                    'email'    => $usuario->email,
                ],
                'total_pendientes' => $query->count()
            ]);

        } catch (\Exception $e) {
            \Log::error('Error al obtener diagnósticos pendientes', ['tecnico_id' => auth()->id(), 'error' => $e->getMessage()]);
            return $this->errorResponse('Error al obtener los diagnósticos pendientes', $e);
        }
    }

    // ============================================
    // PIEZAS DISPONIBLES
    // ============================================

    public function getPiezasDisponibles(): JsonResponse
    {
        try {
            $piezas = Pieza::with('categoria')
                ->where('stock', '>', 0)
                ->select('id_pieza', 'nombre_pieza', 'precio', 'stock', 'id_categoria')
                ->orderBy('nombre_pieza')
                ->get();

            return response()->json(['success' => true, 'data' => $piezas]);

        } catch (\Exception $e) {
            return $this->errorResponse('Error al obtener piezas disponibles', $e);
        }
    }

    // ============================================
    // tecnicos activos
    // ============================================
    /**
     * Obtener técnicos disponibles para asignación/reasignación
     */
    /**
 * Obtener técnicos disponibles para diagnósticos (con en_linea)
 */
/**
 * Obtener técnicos disponibles para diagnósticos (con en_linea)
 */public function tecnicosDisponibles(): JsonResponse
{
    try {
        $tecnicos = Usuario::tecnicos()
            ->where('activo', true)
            ->withCount(['diagnosticos as carga_actual' => function($query) {
                // MISMA condición que en pendientesPorTecnico
                $query->whereNotIn('estado', [
                    'LISTO_PARA_RETIRAR', 
                    'RECHAZADO', 
                    'NO_REPARADO', 
                    'EXPIRADO',
                    'PAGADO'
                ]);
            }])
            ->orderBy('carga_actual', 'asc')
            ->get(['id_usuario', 'nombre', 'apellido', 'correo', 'en_linea']);

        \Log::info('Técnicos disponibles para diagnósticos:', [
            'tecnicos' => $tecnicos->map(function($t) {
                return [
                    'id' => $t->id_usuario,
                    'nombre' => $t->nombre,
                    'carga_actual' => $t->carga_actual
                ];
            })
        ]);

        return response()->json([
            'success' => true,
            'data' => $tecnicos
        ]);
    } catch (\Exception $e) {
        \Log::error('Error en tecnicosDisponibles', [
            'error' => $e->getMessage()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Error al obtener técnicos disponibles'
        ], 500);
    }
}
    /**
     * Reasignar un diagnóstico a otro técnico
     */
    public function reasignar(Request $request, $id): JsonResponse
    {
        $request->validate([
            'id_tecnico_nuevo' => 'required|integer|exists:usuario,id_usuario'
        ]);

        try {
            $diagnostico = Diagnostico::findOrFail($id);
            
            // Guardar técnico anterior para el log
            $tecnicoAnterior = $diagnostico->id_usuario;
            
            // Verificar que el nuevo técnico sea válido y esté activo
            $nuevoTecnico = Usuario::tecnicos()
                ->activos() //cambiar en caso por ahora 
                ->find($request->id_tecnico_nuevo);
                
            if (!$nuevoTecnico) {
                return response()->json([
                    'success' => false,
                    'message' => 'El técnico seleccionado no es válido o no está activo'
                ], 400);
            }
            
            // Verificar que el diagnóstico esté en estado reasignable
            $estadosReasignables = ['ESPERANDO_DIAGNOSTICO', 'EN_REVISION', 'APROBADO'];
            if (!in_array($diagnostico->estado, $estadosReasignables)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este diagnóstico no se puede reasignar en su estado actual'
                ], 400);
            }
            
            // ¡ESTO ES LO ÚNICO REALMENTE NECESARIO!
            $diagnostico->id_usuario = $request->id_tecnico_nuevo;
            $diagnostico->save();
            
            \Log::info('Diagnóstico reasignado', [
                'id_diagnostico' => $id,
                'tecnico_anterior' => $tecnicoAnterior,
                'tecnico_nuevo' => $request->id_tecnico_nuevo,
                'reasignado_por' => auth()->id()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Diagnóstico reasignado exitosamente',
                'data' => $diagnostico->load('usuario')
            ]);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Diagnóstico no encontrado'
            ], 404);
        } catch (\Exception $e) {
            \Log::error('Error al reasignar diagnóstico', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al reasignar el diagnóstico'
            ], 500);
        }
    }

    /**
     * Reasignar múltiples diagnósticos a otro técnico
     */
    public function reasignarMultiples(Request $request): JsonResponse
    {
        $request->validate([
            'ids_diagnosticos' => 'required|array|min:1',
            'ids_diagnosticos.*' => 'integer|exists:diagnostico,id_diagnostico',
            'id_tecnico_nuevo' => 'required|integer|exists:usuario,id_usuario'
        ]);

        try {
            // Verificar que el nuevo técnico sea válido
            $nuevoTecnico = Usuario::tecnicos()
                // ->activos()
                ->find($request->id_tecnico_nuevo);
                
            if (!$nuevoTecnico) {
                return response()->json([
                    'success' => false,
                    'message' => 'El técnico seleccionado no es válido o no está activo'
                ], 400);
            }
            
            $reasignados = [];
            $noReasignados = [];
            
            foreach ($request->ids_diagnosticos as $idDiagnostico) {
                $diagnostico = Diagnostico::find($idDiagnostico);
                
                if (!$diagnostico) {
                    $noReasignados[] = [
                        'id' => $idDiagnostico,
                        'motivo' => 'No encontrado'
                    ];
                    continue;
                }
                
                // Verificar estado reasignable
                $estadosReasignables = ['ESPERANDO_DIAGNOSTICO', 'EN_REVISION', 'APROBADO'];
                if (!in_array($diagnostico->estado, $estadosReasignables)) {
                    $noReasignados[] = [
                        'id' => $idDiagnostico,
                        'motivo' => 'Estado no reasignable: ' . $diagnostico->estado
                    ];
                    continue;
                }
                
                $tecnicoAnterior = $diagnostico->id_usuario;
                $diagnostico->id_usuario = $request->id_tecnico_nuevo;
                $diagnostico->save();
                
                $reasignados[] = $idDiagnostico;
                
                \Log::info('Diagnóstico reasignado (múltiple)', [
                    'id_diagnostico' => $idDiagnostico,
                    'tecnico_anterior' => $tecnicoAnterior,
                    'tecnico_nuevo' => $request->id_tecnico_nuevo
                ]);
            }
            
            return response()->json([
                'success' => true,
                'message' => count($reasignados) . ' diagnósticos reasignados exitosamente',
                'reasignados' => $reasignados,
                'no_reasignados' => $noReasignados
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error al reasignar múltiples diagnósticos', [
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al reasignar los diagnósticos'
            ], 500);
        }
    }

    /**
     * Obtener diagnósticos pendientes de un técnico específico
     */
    /**
 * Obtener diagnósticos activos de un técnico específico
 */
/**
 * Obtener diagnósticos activos de un técnico específico
 * Excluye diagnósticos que tienen TODAS las piezas en PAGADO
 */
public function pendientesPorTecnico($idTecnico): JsonResponse
{
    try {
        // 1. Obtener diagnósticos activos por estado
        $diagnosticos = Diagnostico::where('id_usuario', $idTecnico)
            ->whereNotIn('estado', [
                'LISTO_PARA_RETIRAR', 
                'RECHAZADO', 
                'NO_REPARADO', 
                'EXPIRADO',
                'PAGADO'
            ])
            ->with([
                'ingreso.dispositivo.modelo.marca',
                'ingreso.dispositivo.cliente',
                'piezas'
            ])
            ->orderBy('fecha_expiracion', 'asc')
            ->get();
        
        // 2. LOG: Ver qué diagnósticos vienen ANTES del filtro
        \Log::info('=== DIAGNÓSTICOS ANTES DE FILTRAR ===', [
            'tecnico_id' => $idTecnico,
            'total_raw' => $diagnosticos->count(),
            'diagnosticos' => $diagnosticos->map(function($d) {
                return [
                    'id' => $d->id_diagnostico,
                    'estado_diagnostico' => $d->estado,
                    'piezas_count' => $d->piezas->count(),
                    'estados_piezas' => $d->piezas->map(function($p) {
                        return $p->pivot->estado;
                    })->toArray()
                ];
            })
        ]);
        
        // 3. Filtrar diagnósticos que NO tengan todas las piezas en PAGADO
        $diagnosticosFiltrados = $diagnosticos->filter(function($diagnostico) {
            // Si no tiene piezas, lo incluimos
            if ($diagnostico->piezas->count() === 0) {
                return true;
            }
            
            // Verificar si TODAS las piezas están en estado PAGADO
            $todasPagadas = $diagnostico->piezas->every(function($pieza) {
                return $pieza->pivot->estado === 'PAGADO';
            });
            
            return !$todasPagadas;
        })->values();
        
        // 4. LOG: Ver qué diagnósticos quedaron DESPUÉS del filtro
        \Log::info('=== DIAGNÓSTICOS DESPUÉS DE FILTRAR ===', [
            'tecnico_id' => $idTecnico,
            'total_filtrados' => $diagnosticosFiltrados->count(),
            'ids_filtrados' => $diagnosticosFiltrados->pluck('id_diagnostico')->toArray()
        ]);
            
        return response()->json([
            'success' => true,
            'data' => $diagnosticosFiltrados,
            'total' => $diagnosticosFiltrados->count(),
            'debug_total_raw' => $diagnosticos->count()  // Opcional: para debug
        ]);
        
    } catch (\Exception $e) {
        \Log::error('Error al obtener diagnósticos pendientes del técnico', [
            'id_tecnico' => $idTecnico,
            'error' => $e->getMessage()
        ]);
        return response()->json([
            'success' => false,
            'message' => 'Error al obtener diagnósticos pendientes'
        ], 500);
    }
}

    // ============================================
    // ESTADÍSTICAS
    // ============================================

    public function estadisticas(): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data'    => [
                    'total'                     => Diagnostico::count(),
                    'por_estado'                => Diagnostico::selectRaw('estado, COUNT(*) as cantidad')
                                                    ->groupBy('estado')->get()->pluck('cantidad', 'estado'),
                    'por_gravedad'              => Diagnostico::selectRaw('gravedad, COUNT(*) as cantidad')
                                                    ->whereNotNull('gravedad')->groupBy('gravedad')->get()->pluck('cantidad', 'gravedad'),
                    'diagnosticos_con_pieza'    => Diagnostico::whereNotNull('id_pieza')->count(),
                    'diagnosticos_sin_pieza'    => Diagnostico::whereNull('id_pieza')->count(),
                    'piezas_mas_diagnosticadas' => \DB::table('diagnostico')
                                                    ->join('pieza', 'diagnostico.id_pieza', '=', 'pieza.id_pieza')
                                                    ->select('pieza.id_pieza', 'pieza.nombre_pieza', \DB::raw('COUNT(*) as total'))
                                                    ->whereNotNull('diagnostico.id_pieza')
                                                    ->groupBy('pieza.id_pieza', 'pieza.nombre_pieza')
                                                    ->orderBy('total', 'desc')
                                                    ->limit(5)
                                                    ->get(),
                    'ultimo_mes'                => Diagnostico::where('fecha_expiracion', '>=', now()->subMonth())->count(),
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error al obtener estadísticas', ['error' => $e->getMessage()]);
            return $this->errorResponse('Error al obtener estadísticas', $e);
        }
    }
     public function obtenerCargaTrabajoTecnicos()
{
    try {
        // Obtener todos los técnicos activos
        $tecnicos = Usuario::where('es_tecnico', true)
            ->where('en_linea', true)
            ->get(['id_usuario', 'nombre', 'apellido', 'correo','en_linea']);

        $resultado = $tecnicos->map(function ($tecnico) {
            
            //  DIAGNÓSTICOS PENDIENTES
            $diagnosticosPendientes = Diagnostico::where('id_usuario', $tecnico->id_usuario)
                  ->whereIn('estado', ['ESPERANDO_DIAGNOSTICO', 'EN_REVISION', 'APROBADO'])
                ->count();

            // ⏰ REPARACIONES PENDIENTES (a través de Reparacion)
            $reparacionesPendientes = ReparacionMultiple::whereHas('reparacion', function ($q) use ($tecnico) {
                    $q->where('id_usuario', $tecnico->id_usuario);
                })
                ->where('estado', 'PENDIENTE')
                ->count();

            //  REPARACIONES ACTIVAS (en curso)
            $reparacionesActivas = ReparacionMultiple::whereHas('reparacion', function ($q) use ($tecnico) {
                    $q->where('id_usuario', $tecnico->id_usuario);
                })
                ->whereIn('estado', ['EN_REPARACION', 'ESPERANDO_PIEZA'])
                ->count();

            // Total de trabajos activos
            $totalActivo = $diagnosticosPendientes + $reparacionesPendientes + $reparacionesActivas;

            return [
                'id_usuario'              => $tecnico->id_usuario,
                'nombre'                  => $tecnico->nombre,
                'apellido'                => $tecnico->apellido,
                'correo'                  => $tecnico->correo,
                 'en_linea'                => (bool) $tecnico->en_linea,
                'diagnosticos_pendientes' => $diagnosticosPendientes,
                'reparaciones_pendientes' => $reparacionesPendientes,
                'reparaciones_activas'    => $reparacionesActivas,
                'total_activo'            => $totalActivo,
            ];
        });

        // Ordenar por prioridad (menor carga primero)
        $ordenados = $resultado->sortBy(function ($tecnico) {
            return [
                $tecnico['diagnosticos_pendientes'],
                $tecnico['reparaciones_pendientes'],
                $tecnico['reparaciones_activas'],
                $tecnico['total_activo']
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $ordenados
        ], 200);

    } catch (\Exception $e) {
        \Log::error('Error en obtenerCargaTrabajoTecnicos: ' . $e->getMessage());
        
        return response()->json([
            'success' => false,
            'message' => 'Error al obtener carga de trabajo de técnicos',
            'error' => $e->getMessage()
        ], 500);
    }
}
    
    // ============================================
// NUEVOS MÉTODOS PARA GESTIÓN DE PIEZAS MÚLTIPLES
// ============================================

/**
 * Mostrar diagnóstico con todas sus piezas asociadas
 */
/**
 * Mostrar diagnóstico con todas sus piezas asociadas
 */
public function showWithPiezas($id): JsonResponse
{
    try {
        $diagnostico = Diagnostico::with([
            'ingreso.dispositivo.modelo.marca',
            'ingreso.cliente',
            'usuario',
            'precioReparacion.categoria',
            'reparacion',
            'piezas'  // ← Esta relación ya está definida en el modelo
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $diagnostico
        ]);

    } catch (ModelNotFoundException $e) {
        return $this->notFoundResponse('Diagnóstico no encontrado');
    } catch (\Exception $e) {
        \Log::error('Error en showWithPiezas', ['id' => $id, 'error' => $e->getMessage()]);
        return $this->errorResponse('Error al obtener el diagnóstico con piezas', $e);
    }
}
/**
 * Agregar una pieza al diagnóstico
 */
public function agregarPieza(Request $request, $id): JsonResponse
{
    try {
        $request->validate([
            'id_pieza' => 'required|integer|exists:pieza,id_pieza',
            'comentario' => 'nullable|string|max:500'
        ]);

        $diagnostico = Diagnostico::findOrFail($id);

        // Verificar que el diagnóstico esté en estado editable
        if ($diagnostico->estado !== 'EN_REVISION') {
            return response()->json([
                'success' => false,
                'message' => 'No se pueden agregar piezas a un diagnóstico que ya fue enviado a aprobación'
            ], 400);
        }

        // ✅ CORREGIDO: Especificar la tabla en la consulta para evitar ambigüedad
        $existe = $diagnostico->piezas()
            ->where('diagnostico_pieza.id_pieza', $request->id_pieza)  // ← Especificar tabla
            ->exists();

        if ($existe) {
            return response()->json([
                'success' => false,
                'message' => 'Esta pieza ya está asociada al diagnóstico'
            ], 400);
        }
        $pieza = Pieza::findOrFail($request->id_pieza);
        $precioBase = $pieza->precio;
        $manoObra = $pieza->categoria->mano_obra ?? 0;
        $precioFinal = round($precioBase + ($precioBase * ($manoObra / 100)), 2);
        // Agregar la pieza al diagnóstico
        $diagnostico->piezas()->attach($request->id_pieza, [
            'costo' => $precioFinal,
            'comentario' => $request->comentario ?? null,
            'estado' => 'PENDIENTE'
        ]);

        // Recalcular el costo total del diagnóstico
        $nuevoCosto = $diagnostico->piezas()
            ->wherePivot('estado', 'PENDIENTE')
            ->sum('costo');
        
        $diagnostico->costo = $nuevoCosto;
        $diagnostico->save();

        \Log::info('Pieza agregada al diagnóstico', [
            'id_diagnostico' => $id,
            'id_pieza' => $request->id_pieza,
            'costo_pieza' => $request->costo,
            'costo_total_nuevo' => $nuevoCosto
        ]);

        $diagnostico->load('piezas');

        return response()->json([
            'success' => true,
            'message' => 'Pieza agregada exitosamente',
            'data' => $diagnostico
        ], 201);

    } catch (ModelNotFoundException $e) {
        return $this->notFoundResponse('Diagnóstico no encontrado');
    } catch (\Exception $e) {
        \Log::error('Error al agregar pieza', ['id' => $id, 'error' => $e->getMessage()]);
        return response()->json([
            'success' => false,
            'message' => 'Error al agregar la pieza',
            'error' => $e->getMessage()
        ], 500);
    }
}

/**
 * Eliminar una pieza del diagnóstico
 */
/**
 * Eliminar una pieza del diagnóstico
 */
public function eliminarPieza($id, $piezaId): JsonResponse
{
    try {
        $diagnostico = Diagnostico::findOrFail($id);

        // Verificar que el diagnóstico esté en estado editable
        if ($diagnostico->estado !== 'ESPERANDO_DIAGNOSTICO') {
            return response()->json([
                'success' => false,
                'message' => 'No se pueden eliminar piezas de un diagnóstico que ya fue enviado a aprobación'
            ], 400);
        }

        // ✅ CORREGIDO: Especificar la tabla en la consulta
        $existe = $diagnostico->piezas()
            ->where('diagnostico_pieza.id_pieza', $piezaId)  // ← Especificar tabla
            ->exists();

        if (!$existe) {
            return response()->json([
                'success' => false,
                'message' => 'La pieza no está asociada a este diagnóstico'
            ], 404);
        }

        // Eliminar la relación
        $diagnostico->piezas()->detach($piezaId);

        // Recalcular costo total
        $nuevoCosto = $diagnostico->piezas()
            ->wherePivot('estado', 'PENDIENTE')
            ->sum('costo');
        
        $diagnostico->costo = $nuevoCosto;
        $diagnostico->save();

        \Log::info('Pieza eliminada del diagnóstico', [
            'id_diagnostico' => $id,
            'id_pieza' => $piezaId,
            'costo_total_nuevo' => $nuevoCosto
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pieza eliminada exitosamente',
            'data' => $diagnostico->load('piezas')
        ]);

    } catch (ModelNotFoundException $e) {
        return $this->notFoundResponse('Diagnóstico no encontrado');
    } catch (\Exception $e) {
        \Log::error('Error al eliminar pieza', ['id' => $id, 'piezaId' => $piezaId, 'error' => $e->getMessage()]);
        return $this->errorResponse('Error al eliminar la pieza', $e);
    }
}

/**
 * Actualizar una pieza del diagnóstico (costo o comentario)
 */
public function actualizarPieza(Request $request, $id, $piezaId): JsonResponse
{
    try {
        $request->validate([
            'costo' => 'sometimes|numeric|min:0',
            'comentario' => 'sometimes|string|max:500'
        ]);

        $diagnostico = Diagnostico::findOrFail($id);

        // Verificar que el diagnóstico esté en estado editable
        if ($diagnostico->estado !== 'ESPERANDO_DIAGNOSTICO') {
            return response()->json([
                'success' => false,
                'message' => 'No se pueden modificar piezas de un diagnóstico que ya fue enviado a aprobación'
            ], 400);
        }

        // Verificar si la pieza está asociada
        $relacion = $diagnostico->piezas()
            ->where('id_pieza', $piezaId)
            ->first();

        if (!$relacion) {
            return response()->json([
                'success' => false,
                'message' => 'La pieza no está asociada a este diagnóstico'
            ], 404);
        }

        // Actualizar los datos en la tabla pivote
        $updateData = [];
        if ($request->has('costo')) $updateData['costo'] = $request->costo;
        if ($request->has('comentario')) $updateData['comentario'] = $request->comentario;

        if (!empty($updateData)) {
            $diagnostico->piezas()->updateExistingPivot($piezaId, $updateData);
        }

        // Recalcular costo total
        $nuevoCosto = $diagnostico->piezas()
            ->wherePivot('estado', 'PENDIENTE')
            ->sum('costo');
        
        // Actualizar la columna 'costo' del diagnóstico
        $diagnostico->costo = $nuevoCosto;
        $diagnostico->save();

        \Log::info('Pieza actualizada en diagnóstico', [
            'id_diagnostico' => $id,
            'id_pieza' => $piezaId,
            'updateData' => $updateData,
            'costo_total_nuevo' => $nuevoCosto
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pieza actualizada exitosamente',
            'data' => $diagnostico->load('piezas')
        ]);

    } catch (ModelNotFoundException $e) {
        return $this->notFoundResponse('Diagnóstico no encontrado');
    } catch (\Exception $e) {
        \Log::error('Error al actualizar pieza', ['id' => $id, 'piezaId' => $piezaId, 'error' => $e->getMessage()]);
        return $this->errorResponse('Error al actualizar la pieza', $e);
    }
}

/**
 * Enviar diagnóstico a aprobación del cliente
 */
public function enviarAprobacion($id): JsonResponse
{
    try {
        $diagnostico = Diagnostico::with(['piezas'])->findOrFail($id);

        // Verificar estado actual
        if ($diagnostico->estado !== 'EN_REVISION') {
            return response()->json([
                'success' => false,
                'message' => 'El diagnóstico ya fue enviado a aprobación o no está en estado editable'
            ], 400);
        }

        // Verificar que tenga al menos una pieza
        $totalPiezas = $diagnostico->piezas()->count();
        if ($totalPiezas === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Debe agregar al menos una pieza antes de enviar a aprobación'
            ], 400);
        }

        // Cambiar estado del diagnóstico y agregar fecha_fin
        $estadoAnterior = $diagnostico->estado;
        $diagnostico->estado = 'ESPERANDO_APROBACION';
        $diagnostico->fecha_fin_revision = now(); // 👈 ESTA ES LA LÍNEA QUE AGREGA LA FECHA
        $diagnostico->save();

        \Log::info('Diagnóstico enviado a aprobación', [
            'id_diagnostico' => $id,
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => 'ESPERANDO_APROBACION',
            'total_piezas' => $totalPiezas,
            'costo_total' => $diagnostico->costo,
            'fecha_fin' => $diagnostico->fecha_fin_revision  // 👈 También loguea la fecha
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Diagnóstico enviado a aprobación exitosamente',
            'data' => $diagnostico->load(['usuario', 'ingreso', 'piezas'])
        ]);

    } catch (ModelNotFoundException $e) {
        return $this->notFoundResponse('Diagnóstico no encontrado');
    } catch (\Exception $e) {
        \Log::error('Error al enviar diagnóstico a aprobación', ['id' => $id, 'error' => $e->getMessage()]);
        return $this->errorResponse('Error al enviar el diagnóstico a aprobación', $e);
    }
}

/**
 * Calcular costo total del diagnóstico basado en sus piezas
 */
public function calcularCosto($id): JsonResponse
{
    try {
        $diagnostico = Diagnostico::findOrFail($id);

        $costoPendiente = $diagnostico->piezas()
            ->wherePivot('estado', 'PENDIENTE')
            ->sum('costo');

        $costoAprobado = $diagnostico->piezas()
            ->wherePivot('estado', 'APROBADO')
            ->sum('costo');

        $costoRechazado = $diagnostico->piezas()
            ->wherePivot('estado', 'RECHAZADO')
            ->sum('costo');
            
        $costoTotal = $costoPendiente + $costoAprobado;

        return response()->json([
            'success' => true,
            'data' => [
                'costo_diagnostico' => $diagnostico->costo, // ← columna del diagnóstico
                'costo_pendiente' => $costoPendiente,
                'costo_aprobado' => $costoAprobado,
                'costo_rechazado' => $costoRechazado,
                'costo_total_piezas' => $costoTotal,
                'total_piezas' => $diagnostico->piezas()->count(),
                'piezas_pendientes' => $diagnostico->piezas()->wherePivot('estado', 'PENDIENTE')->count(),
                'piezas_aprobadas' => $diagnostico->piezas()->wherePivot('estado', 'APROBADO')->count(),
                'piezas_rechazadas' => $diagnostico->piezas()->wherePivot('estado', 'RECHAZADO')->count()
            ]
        ]);

    } catch (ModelNotFoundException $e) {
        return $this->notFoundResponse('Diagnóstico no encontrado');
    } catch (\Exception $e) {
        \Log::error('Error al calcular costo', ['id' => $id, 'error' => $e->getMessage()]);
        return $this->errorResponse('Error al calcular el costo total', $e);
    }
}
    
    





    // ============================================
    // MÉTODOS PRIVADOS
    // ============================================

    /**
     * Crea Reparacion y ReparacionMultiple cuando el cliente aprueba el diagnóstico.
     */
  private function crearReparacionDesdeAprobacion(Diagnostico $diagnostico): void
{
    \Log::info('=== crearReparacionDesdeAprobacion ===');
    
    // Verificar si ya existe reparación
    $reparacionExistente = Reparacion::where('id_diagnostico', $diagnostico->id_diagnostico)->first();
    if ($reparacionExistente) {
        \Log::info('Ya existe reparación, saliendo');
        return;
    }

    // ✅ PASO 1: Aprobar todas las piezas pendientes
    \DB::table('diagnostico_pieza')
        ->where('id_diagnostico', $diagnostico->id_diagnostico)
        ->whereIn('estado', ['PENDIENTE', 'ESPERANDO_APROBACION'])
        ->update([
            'estado' => 'APROBADO',
            'fecha_aprobacion' => now()
        ]);

    // ✅ PASO 2: Crear la reparación principal
    $reparacion = Reparacion::create([
        'id_diagnostico' => $diagnostico->id_diagnostico,
        'id_usuario'     => $diagnostico->id_usuario,
        'id_ingreso'     => null,
        'comentario'     => 'Reparación creada automáticamente tras aprobación del cliente'
    ]);

    // ✅ PASO 3: Obtener todas las piezas aprobadas con sus datos del pivote
    $piezasAprobadas = $diagnostico->piezas()
        ->where('diagnostico_pieza.estado', 'APROBADO')
        ->get();
    
    \Log::info('Piezas aprobadas encontradas', ['cantidad' => $piezasAprobadas->count()]);

    // ✅ PASO 4: Crear ReparacionMultiple por cada pieza aprobada
    foreach ($piezasAprobadas as $pieza) {
        // El costo en el pivote YA INCLUYE la mano de obra (es el precio final)
        $costoTotalDesdePivote = $pieza->pivot->costo ?? $pieza->precio ?? 0;
        
        // Obtener el porcentaje de mano de obra de la categoría
        $manoObraPorcentaje = $pieza->categoria->mano_obra ?? 0;
        
        // Calcular el precio de la pieza SOLA (sin mano de obra)
        // Fórmula: precio_pieza = costo_total / (1 + (mano_obra/100))
        if ($manoObraPorcentaje > 0) {
            $precioPiezaSolo = round($costoTotalDesdePivote / (1 + ($manoObraPorcentaje / 100)), 2);
        } else {
            $precioPiezaSolo = $costoTotalDesdePivote;
        }
        
        \Log::info('Creando ReparacionMultiple', [
            'id_pieza' => $pieza->id_pieza,
            'nombre_pieza' => $pieza->nombre_pieza,
            'costo_total_desde_pivote' => $costoTotalDesdePivote,
            'precio_pieza_solo' => $precioPiezaSolo,
            'mano_obra_porcentaje' => $manoObraPorcentaje,
        ]);
        
        ReparacionMultiple::create([
            'id_reparacion'        => $reparacion->id_reparacion,
            'id_pieza'             => $pieza->id_pieza,
            'estado'               => 'PENDIENTE',
            'precio_pieza_momento' => $precioPiezaSolo,      // ✅ Solo la pieza
            'mano_obra_momento'    => $manoObraPorcentaje,   // ✅ Porcentaje
            'precio_total'         => $costoTotalDesdePivote, // ✅ Total (pieza + mano)
            'comentario_tecnico'   => $pieza->pivot->comentario ?? null,
        ]);
    }
    
    // ✅ PASO 5: Actualizar el costo total del diagnóstico
    $costoTotal = $piezasAprobadas->sum(function($pieza) {
        return $pieza->pivot->costo ?? $pieza->precio ?? 0;
    });
    
    $diagnostico->costo = $costoTotal;
    $diagnostico->save();
    
    \Log::info('Reparaciones creadas exitosamente', [
        'id_diagnostico'    => $diagnostico->id_diagnostico,
        'cantidad_piezas'   => $piezasAprobadas->count(),
        'costo_total'       => $costoTotal,
        'id_reparacion'     => $reparacion->id_reparacion
    ]);
}
    /**
     * Genera un mensaje personalizado según los campos actualizados.
     */
    private function generarMensajeActualizacion(array $camposModificados): string
    {
        if (count($camposModificados) !== 1) return 'Diagnóstico actualizado exitosamente';

        return match (array_key_first($camposModificados)) {
            'estado'           => 'Estado del diagnóstico actualizado exitosamente',
            'gravedad'         => 'Nivel de gravedad actualizado exitosamente',
            'costo'            => 'Costo actualizado exitosamente',
            'costo_reparacion' => 'Costo de reparación actualizado exitosamente',
            'observacion'      => 'Observación actualizada exitosamente',
            'causa_detectada'  => 'Causa detectada actualizada exitosamente',
            'solucion'         => 'Solución actualizada exitosamente',
            'fecha_expiracion' => 'Fecha de expiración actualizada exitosamente',
            'id_precio_r'      => 'Precio de reparación actualizado exitosamente',
            'id_pieza'         => 'Pieza asignada actualizada exitosamente',
            default            => 'Diagnóstico actualizado exitosamente',
        };
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