<?php

namespace App\Http\Controllers;

use App\Http\Requests\Ingreso_d\StoreIngresoDRequest;
use App\Http\Requests\Ingreso_d\UpdateIngresoDRequest;
use App\Models\Dispositivo;
use App\Models\Ingreso_d;
use App\Models\Usuario;
use App\Models\ReparacionMultiple;
use App\Models\Reparacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Http\Request;  
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class IngresoController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $ingresos = Ingreso_d::with(['dispositivo.cliente', 'dispositivo.modelo.marca', 'usuario'])
                ->get();
            
            $ingresos->each(function ($ingreso) {
                $ingreso->foto_frontal_url = $ingreso->foto_frontal 
                    ? Storage::disk('public')->url($ingreso->foto_frontal)
                    : null;
                $ingreso->foto_trasera_url = $ingreso->foto_trasera 
                    ? Storage::disk('public')->url($ingreso->foto_trasera)
                    : null;
            });
            
            return response()->json([
                'success' => true,
                'data' => $ingresos
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error en IngresoController@index: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los ingresos'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(StoreIngresoDRequest $request): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            Log::info('🎯 ===== INICIANDO STORE =====');
            
            $validated = $request->validated();
            Log::info('✅ Datos validados:', $validated);
            
            $validated['sim'] = (bool) ($validated['sim'] ?? false);
            $validated['memoria_sd'] = (bool) ($validated['memoria_sd'] ?? false);
            $validated['revision_tecnica'] = (bool) ($validated['revision_tecnica'] ?? false);
            
            $dispositivo = DB::table('dispositivo')
                ->where('id_dispositivo', $validated['id_dispositivo'])
                ->first();
                
            if (!$dispositivo) {
                Log::warning('Dispositivo no encontrado:', ['id' => $validated['id_dispositivo']]);
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'El dispositivo no existe'
                ], 404);
            }
            
            $usuario = DB::table('usuario')
                ->where('id_usuario', $validated['id_usuario'])
                ->first();
                
            if (!$usuario) {
                Log::warning('Usuario no encontrado:', ['id' => $validated['id_usuario']]);
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'El usuario no existe'
                ], 404);
            }
            
            $fotoFrontalPath = null;
            $fotoTraseraPath = null;
            
            if ($request->hasFile('foto_frontal')) {
                try {
                    $fotoFrontalPath = $this->guardarImagen($request->file('foto_frontal'), 'frontales');
                } catch (\Exception $e) {
                    Log::error('Error guardando frontal:', ['error' => $e->getMessage()]);
                }
            }
            
            if ($request->hasFile('foto_trasera')) {
                try {
                    $fotoTraseraPath = $this->guardarImagen($request->file('foto_trasera'), 'traseras');
                } catch (\Exception $e) {
                    Log::error('Error guardando trasera:', ['error' => $e->getMessage()]);
                }
            }
            
            $ingresoData = [
                'id_dispositivo' => $validated['id_dispositivo'],
                'id_usuario' => $validated['id_usuario'],
                'memoria_sd' => $validated['memoria_sd'],
                'sim' => $validated['sim'],
                'estado_del_ingreso' => $validated['estado_del_ingreso'] ?? 'ESPERANDO DIAGNOSTICO',
                'comentario_cliente' => $validated['comentario_cliente'] ?? '',
                'revision_tecnica' => $validated['revision_tecnica'],
                'foto_frontal' => $fotoFrontalPath,
                'foto_trasera' => $fotoTraseraPath,
                'estado' => 'TALLER'
            ];
            
            $ingreso = Ingreso_d::create($ingresoData);
            
            $ingreso->load(['dispositivo.cliente', 'dispositivo.modelo.marca', 'usuario']);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Ingreso registrado exitosamente',
                'data' => $ingreso
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('ERROR CRÍTICO:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar el ingreso: ' . $e->getMessage()
            ], 500);
        }
    }

    // ============================================
    // MÉTODOS PARA CAMBIAR ESTADO
    // ============================================

    public function cambiarEstado(Request $request, $id): JsonResponse
    {
        try {
            $request->validate([
                'estado' => 'required|in:TALLER,RETIRADO'
            ]);

            $ingreso = Ingreso_d::findOrFail($id);
            $ingreso->estado = $request->estado;
            $ingreso->save();

            return response()->json([
                'success' => true,
                'message' => 'Estado actualizado correctamente',
                'data' => $ingreso
            ]);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ingreso no encontrado'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el estado'
            ], 500);
        }
    }

public function marcarRetirado($id): JsonResponse
{
    try {
        Log::info('🔍 [RETIRO] PASO 1: Iniciando marcarRetirado', ['id_ingreso' => $id]);
        
        $ingreso = Ingreso_d::with([
            'reparacion', 
            'diagnosticos'
        ])->findOrFail($id);
        
        Log::info('🔍 [RETIRO] PASO 2: Ingreso encontrado', [
            'id_ingreso' => $ingreso->id_ingreso,
            'estado_actual' => $ingreso->estado,
            'tiene_reparacion' => $ingreso->reparacion ? 'si' : 'no',
            'tiene_diagnosticos' => $ingreso->diagnosticos ? $ingreso->diagnosticos->count() : 0
        ]);
        
        if ($ingreso->estado === 'RETIRADO') {
            Log::info('⚠️ [RETIRO] El dispositivo ya estaba retirado');
            return response()->json([
                'success' => false,
                'message' => 'Este dispositivo ya fue retirado'
            ], 400);
        }
        
        // ✅ VERIFICAR casos especiales que permiten retiro aunque no esté "listo_para_retirar"
        $diagnosticoRechazado = false;
        $reparacionCancelada = false;
        
        // Verificar diagnóstico rechazado
        if ($ingreso->diagnosticos && $ingreso->diagnosticos->count() > 0) {
            foreach ($ingreso->diagnosticos as $diagnostico) {
                if ($diagnostico->estado === 'RECHAZADO') {
                    $diagnosticoRechazado = true;
                    break;
                }
            }
        }
        
        // Verificar reparación cancelada (todas las piezas rechazadas)
        if ($ingreso->reparacion && $ingreso->reparacion->estado_general === 'CANCELADO') {
            $reparacionCancelada = true;
        }
        
        // ✅ Solo verificar listo_para_retirar si NO hay diagnóstico rechazado NI reparación cancelada
        if (!$diagnosticoRechazado && !$reparacionCancelada && !$ingreso->listo_para_retirar) {
            Log::info('⚠️ [RETIRO] El dispositivo no está listo para retirar');
            return response()->json([
                'success' => false,
                'message' => 'El dispositivo no está listo para retirar.'
            ], 400);
        }
        
        Log::info('🔍 [RETIRO] PASO 3: Actualizando estado a RETIRADO', [
            'diagnostico_rechazado' => $diagnosticoRechazado,
            'reparacion_cancelada' => $reparacionCancelada
        ]);
        
        $ingreso->estado = 'RETIRADO';
        $ingreso->save();

        Log::info('✅ [RETIRO] Estado actualizado correctamente');
        
        // ✅ Crear testimonio solo si hay reparación (no si fue rechazado o cancelado)
        if (!$diagnosticoRechazado && !$reparacionCancelada) {
            $this->crearTestimonioPendiente($ingreso);
        } else {
            Log::info('⚠️ [RETIRO] No se crea testimonio', [
                'diagnostico_rechazado' => $diagnosticoRechazado,
                'reparacion_cancelada' => $reparacionCancelada
            ]);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Dispositivo marcado como retirado',
            'data' => $ingreso
        ]);
        
    } catch (ModelNotFoundException $e) {
        Log::error('❌ [RETIRO] Ingreso no encontrado', ['id' => $id]);
        return response()->json([
            'success' => false,
            'message' => 'Ingreso no encontrado'
        ], 404);
    } catch (\Exception $e) {
        Log::error('❌ [RETIRO] Error inesperado', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Error al marcar como retirado: ' . $e->getMessage()
        ], 500);
    }
}
    public function enTaller(): JsonResponse
    {
        try {
            $ingresos = Ingreso_d::where('estado', 'TALLER')
                ->with(['dispositivo.modelo.marca', 'dispositivo.cliente', 'usuario'])
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $ingresos
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener ingresos en taller'
            ], 500);
        }
    }

    public function retirados(): JsonResponse
    {
        try {
            $ingresos = Ingreso_d::where('estado', 'RETIRADO')
                ->with(['dispositivo.modelo.marca', 'dispositivo.cliente', 'usuario'])
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $ingresos
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener ingresos retirados'
            ], 500);
        }
    }

    // ============================================
    // MÉTODOS PARA RETIRO POR CLIENTE
    // ============================================

    public function porCliente($idCliente): JsonResponse
    {
        try {
            $ingresos = Ingreso_d::whereHas('dispositivo', function($q) use ($idCliente) {
                $q->where('id_cliente', $idCliente);
            })
            ->with([
                'dispositivo.modelo.marca',
                'dispositivo.cliente',
                'diagnosticos',
                'reparacion'
            ])
            ->orderBy('fecha_ingreso', 'desc')
            ->get();
            
            return response()->json([
                'success' => true,
                'data' => $ingresos
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error en porCliente', [
                'id_cliente' => $idCliente,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los ingresos del cliente'
            ], 500);
        }
    }

    public function listosParaRetirar(): JsonResponse
    {
        try {
            $ingresos = Ingreso_d::where('estado', 'TALLER')
                ->where(function($q) {
                    $q->whereHas('diagnosticos', function($q2) {
                        $q2->where('estado', 'LISTO_PARA_RETIRAR');
                    })->orWhereHas('reparacion', function($q3) {
                        $q3->where('estado_general', 'TERMINADO');
                    });
                })
                ->with([
                    'dispositivo.modelo.marca',
                    'dispositivo.cliente',
                    'diagnosticos',
                    'reparacion'
                ])
                ->orderBy('fecha_ingreso', 'desc')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $ingresos
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error en listosParaRetirar', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener dispositivos listos para retirar'
            ], 500);
        }
    }

    public function pagarLocal(Request $request, $id): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            $ingreso = Ingreso_d::with([
                'reparacion.reparacionesMultiples',
                'diagnosticos'
            ])->findOrFail($id);

            if ($ingreso->estaRetirado()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este dispositivo ya fue retirado'
                ], 400);
            }

            if (!$ingreso->listo_para_retirar) {
                return response()->json([
                    'success' => false,
                    'message' => 'El dispositivo no está listo para retirar.'
                ], 400);
            }

            $reparacionesActualizadas = 0;

            if ($ingreso->reparacion) {
                $reparacion = $ingreso->reparacion;
                  // ✅ 1. Ver cuántas piezas tiene esta reparación
                $totalPiezas = ReparacionMultiple::where('id_reparacion', $reparacion->id_reparacion)->count();
                \Log::info('📊 Total de piezas encontradas', [
                    'id_reparacion' => $reparacion->id_reparacion,
                    'total' => $totalPiezas
                ]);

                ReparacionMultiple::where('id_reparacion', $reparacion->id_reparacion)
                    ->update(['estado_pago' => 'PAGADO_LOCAL']);

                $reparacion->estado_pago = 'PAGADO_LOCAL';
                $reparacion->save();

                $reparacionesActualizadas++;

                \Log::info('Caso 1 - Reparación directa actualizada', [
                    'id_reparacion' => $reparacion->id_reparacion,
                    'estado_pago'   => $reparacion->estado_pago,
                ]);
            }

            if ($ingreso->diagnosticos->count() > 0) {
                foreach ($ingreso->diagnosticos as $diagnostico) {

                    $reparacion = Reparacion::where('id_diagnostico', $diagnostico->id_diagnostico)
                        ->first();

                    if ($reparacion) {
                        ReparacionMultiple::where('id_reparacion', $reparacion->id_reparacion)
                            ->update(['estado_pago' => 'PAGADO_LOCAL']);

                        $reparacion->estado_pago = 'PAGADO_LOCAL';
                        $reparacion->save();

                        $reparacionesActualizadas++;

                        \Log::info('Caso 2 - Reparación via diagnóstico actualizada', [
                            'id_diagnostico' => $diagnostico->id_diagnostico,
                            'id_reparacion'  => $reparacion->id_reparacion,
                            'estado_pago'    => $reparacion->estado_pago,
                        ]);
                    }

                    $diagnostico->estado = 'PAGADO';
                    $diagnostico->save();
                }
            }

            $ingreso->marcarComoRetirado();
             $ingreso->refresh();
            $this->crearTestimonioPendiente($ingreso);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Dispositivo pagado y retirado exitosamente',
                'data' => [
                    'reparaciones_actualizadas' => $reparacionesActualizadas,
                    'fecha_retiro'              => now()->toDateTimeString()
                ]
            ]);

        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Ingreso no encontrado'], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error en pagarLocal: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    // ============================================
    // OTROS MÉTODOS EXISTENTES
    // ============================================

    private function crearTestimonioPendiente(Ingreso_d $ingreso): void
    {
        $reparacionEncontrada = null;

        if ($ingreso->reparacion) {
            $reparacionEncontrada = $ingreso->reparacion;
        }

        if (!$reparacionEncontrada && $ingreso->diagnosticos && $ingreso->diagnosticos->count() > 0) {
            $diagnostico = $ingreso->diagnosticos->first();
            if ($diagnostico && $diagnostico->reparacion) {
                $reparacionEncontrada = $diagnostico->reparacion;
            }
        }

        if (!$reparacionEncontrada) {
            $reparacionEncontrada = Reparacion::where('id_ingreso', $ingreso->id_ingreso)->first();
        }

        if (!$reparacionEncontrada) {
            Log::warning('⚠️ crearTestimonioPendiente: No se encontró reparación', [
                'id_ingreso' => $ingreso->id_ingreso
            ]);
            return;
        }

        $existe = \App\Models\Testimonio::where('id_reparacion', $reparacionEncontrada->id_reparacion)->exists();

        if (!$existe) {
            \App\Models\Testimonio::create([
                'id_reparacion'         => $reparacionEncontrada->id_reparacion,
                'calificacion_estrella' => null,
                'comentario'            => null,
                'estado'                => 'PENDIENTE',
                'skip_testimonio'       => false
            ]);

            Log::info('✅ Testimonio creado', [
                'id_reparacion' => $reparacionEncontrada->id_reparacion
            ]);
        }
    }

    private function guardarImagen($imagen, $subcarpeta = 'ingresos'): string
    {
        try {
            if (!$imagen->isValid()) {
                throw new \Exception('Archivo de imagen no válido');
            }

            $extension = $imagen->getClientOriginalExtension();
            $nombreArchivo = time() . '_' . uniqid() . '.' . $extension;
            $ruta = "ingresos/{$subcarpeta}/{$nombreArchivo}";
            
            Storage::disk('public')->putFileAs(
                "ingresos/{$subcarpeta}",
                $imagen,
                $nombreArchivo
            );
            
            return $ruta;
            
        } catch (\Exception $e) {
            Log::error('Error al guardar imagen:', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function eliminarImagen($ruta): bool
    {
        if ($ruta && Storage::disk('public')->exists($ruta)) {
            return Storage::disk('public')->delete($ruta);
        }
        return false;
    }

    public function show(string $id): JsonResponse
    {
        try {
            $ingreso = Ingreso_d::with(['dispositivo.cliente', 'dispositivo.modelo.marca', 'usuario'])
                ->find($id);
            
            if (!$ingreso) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ingreso no encontrado'
                ], Response::HTTP_NOT_FOUND);
            }
            
            $ingreso->foto_frontal_url = $ingreso->foto_frontal 
                ? Storage::disk('public')->url($ingreso->foto_frontal)
                : null;
            $ingreso->foto_trasera_url = $ingreso->foto_trasera 
                ? Storage::disk('public')->url($ingreso->foto_trasera)
                : null;
            
            return response()->json([
                'success' => true,
                'data' => $ingreso
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error en IngresoController@show: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el ingreso'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(UpdateIngresoDRequest $request, string $id): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            $ingreso = Ingreso_d::find($id);
            
            if (!$ingreso) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ingreso no encontrado'
                ], Response::HTTP_NOT_FOUND);
            }

            $validated = $request->validated();
            
            if (isset($validated['id_dispositivo'])) {
                $dispositivo = Dispositivo::find($validated['id_dispositivo']);
                if (!$dispositivo) {
                    return response()->json([
                        'success' => false,
                        'message' => 'El dispositivo no existe'
                    ], Response::HTTP_NOT_FOUND);
                }
            }

            if (isset($validated['id_usuario'])) {
                $usuario = Usuario::find($validated['id_usuario']);
                if (!$usuario) {
                    return response()->json([
                        'success' => false,
                        'message' => 'El usuario no existe'
                    ], Response::HTTP_NOT_FOUND);
                }
            }

            if ($request->hasFile('foto_frontal')) {
                if ($ingreso->foto_frontal) {
                    $this->eliminarImagen($ingreso->foto_frontal);
                }
                $validated['foto_frontal'] = $this->guardarImagen($request->file('foto_frontal'), 'frontales');
            }

            if ($request->hasFile('foto_trasera')) {
                if ($ingreso->foto_trasera) {
                    $this->eliminarImagen($ingreso->foto_trasera);
                }
                $validated['foto_trasera'] = $this->guardarImagen($request->file('foto_trasera'), 'traseras');
            }

            $ingreso->update($validated);
            
            DB::commit();
            
            $ingreso->load(['dispositivo.cliente', 'dispositivo.modelo.marca', 'usuario']);
            $ingreso->foto_frontal_url = $ingreso->foto_frontal 
                ? Storage::disk('public')->url($ingreso->foto_frontal)
                : null;
            $ingreso->foto_trasera_url = $ingreso->foto_trasera 
                ? Storage::disk('public')->url($ingreso->foto_trasera)
                : null;
            
            return response()->json([
                'success' => true,
                'message' => 'Ingreso actualizado exitosamente',
                'data' => $ingreso
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error en IngresoController@update: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el ingreso'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            $ingreso = Ingreso_d::find($id);
            
            if (!$ingreso) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ingreso no encontrado'
                ], Response::HTTP_NOT_FOUND);
            }

            if ($ingreso->foto_frontal) {
                $this->eliminarImagen($ingreso->foto_frontal);
            }
            if ($ingreso->foto_trasera) {
                $this->eliminarImagen($ingreso->foto_trasera);
            }

            $ingreso->delete();
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Ingreso eliminado exitosamente'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error en IngresoController@destroy: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el ingreso'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function porDispositivo($idDispositivo, $idTecnico = null): JsonResponse
    {
        $ingresos = Ingreso_d::where('id_dispositivo', $idDispositivo)
            ->with([
                'diagnosticos' => function($q) use ($idTecnico) {
                    $q->select(
                        'id_diagnostico','id_ingreso','estado',
                        'gravedad','causa_detectada','solucion',
                        'costo','fecha_expiracion','id_usuario'
                    );
                    
                    if ($idTecnico) {
                        $q->where('id_usuario', $idTecnico);
                    }
                    
                    $q->with([
                        'usuario:id_usuario,nombre,apellido',
                        'reparacion.reparacionesMultiples' 
                    ]);
                }
            ])
            ->orderBy('fecha_ingreso', 'desc')
            ->get()
            ->map(fn($ingreso) => [
                'id_ingreso'     => $ingreso->id_ingreso,
                'fecha_ingreso'  => $ingreso->fecha_ingreso,
                'estado_ingreso' => $ingreso->estado_del_ingreso,
                'comentario'     => $ingreso->comentario_cliente,
                'diagnosticos'   => $ingreso->diagnosticos,
            ]);

        return response()->json([
            'success' => true,
            'data'    => $ingresos,
        ]);
    }

    public function getByDispositivo(string $dispositivoId): JsonResponse
    {
        try {
            $ingresos = Ingreso_d::where('id_dispositivo', $dispositivoId)
                ->with(['dispositivo.cliente', 'dispositivo.modelo.marca', 'usuario'])
                ->get();
            
            $ingresos->each(function ($ingreso) {
                $ingreso->foto_frontal_url = $ingreso->foto_frontal 
                    ? Storage::disk('public')->url($ingreso->foto_frontal)
                    : null;
                $ingreso->foto_trasera_url = $ingreso->foto_trasera 
                    ? Storage::disk('public')->url($ingreso->foto_trasera)
                    : null;
            });
            
            return response()->json([
                'success' => true,
                'data' => $ingresos
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error en IngresoController@getByDispositivo: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los ingresos del dispositivo'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getByEstado(string $estado): JsonResponse
    {
        try {
            $ingresos = Ingreso_d::where('estado_del_ingreso', $estado)
                ->with(['dispositivo.cliente', 'dispositivo.modelo.marca', 'usuario'])
                ->get();
            
            $ingresos->each(function ($ingreso) {
                $ingreso->foto_frontal_url = $ingreso->foto_frontal 
                    ? Storage::disk('public')->url($ingreso->foto_frontal)
                    : null;
                $ingreso->foto_trasera_url = $ingreso->foto_trasera 
                    ? Storage::disk('public')->url($ingreso->foto_trasera)
                    : null;
            });
            
            return response()->json([
                'success' => true,
                'data' => $ingresos
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error en IngresoController@getByEstado: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los ingresos por estado'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function Revision(): JsonResponse
    {
        try {
            $ingresos = Ingreso_d::with([
                'dispositivo:id_dispositivo,id_cliente,id_modelo,imei',
                'dispositivo.cliente:id_cliente,nombre,apellido,numero_celular',
                'dispositivo.modelo:id_modelo,nombre_modelo,id_marca',
                'dispositivo.modelo.marca:id_marca,marca',
                'usuario:id_usuario,nombre,apellido'
            ])
            ->where('revision_tecnica', true)
            ->orderBy('id_ingreso', 'asc')
            ->get();

            $ingresos->each(function ($ingreso) {
                $ingreso->foto_frontal_url = $ingreso->foto_frontal 
                    ? Storage::disk('public')->url($ingreso->foto_frontal)
                    : null;
                $ingreso->foto_trasera_url = $ingreso->foto_trasera 
                    ? Storage::disk('public')->url($ingreso->foto_trasera)
                    : null;
            });

            return response()->json([
                'success' => true,
                'data' => $ingresos,
                'total' => $ingresos->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener ingresos pendientes de revisión: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar los ingresos pendientes de revisión'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function garantiasRetiradasPorDispositivo($idDispositivo): JsonResponse
    {
        try {
            $ingresos = Ingreso_d::where('id_dispositivo', $idDispositivo)
                ->where('estado', Ingreso_d::ESTADO_RETIRADO)
                ->whereHas('reparacion', fn($q) => $q->where('es_garantia', true))
                ->with([
                    'reparacion' => fn($q) => $q->where('es_garantia', true)
                        ->with('reparacionesMultiples.pieza')
                ])
                ->get()
                ->map(fn($ingreso) => [
                    'id_ingreso'    => $ingreso->id_ingreso,
                    'fecha_ingreso' => $ingreso->fecha_ingreso,
                    'estado'        => $ingreso->estado,
                    'es_garantia'   => true,
                    'reparacion'    => $ingreso->reparacion->first(),
                ]);

            return response()->json(['success' => true, 'data' => $ingresos]);

        } catch (\Exception $e) {
            Log::error('Error en garantiasRetiradasPorDispositivo', [
                'id_dispositivo' => $idDispositivo,
                'error'          => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener garantías retiradas'
            ], 500);
        }
    }
}