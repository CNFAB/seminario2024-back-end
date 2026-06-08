<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePostRequest;
use App\Models\Cliente;
use Illuminate\Http\Request;
use App\Models\Dispositivo;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ClienteController extends Controller
{
    public function index()
    {
        $cliente = Cliente::all(); 
        return $cliente;
    }


    public function store(StorePostRequest $request)
    {
        $validated = $request->validated();
        $cliente = new Cliente;
        $cliente->fill($validated);
        $cliente->save();
        return $cliente;
    }

   public function show(string $id)
{
    $cliente = Cliente::find($id);

    if (!$cliente) {
        return response()->json(['error' => 'Cliente no encontrado'], 404);
    }

    return response()->json([
        'success' => true,
        'data'    => $cliente
    ]);
}

    public function condicion(string $id)
    {
        $cliente = Cliente::where('id_cliente', '>', $id)->get();
        return $cliente;
    }

    /**
     * Actualizar datos del perfil del cliente
     * PUT /cliente/{id}
     *
     * Acepta cualquier combinación de:
     *   nombre, apellido, correo, numero_celular, direccion, contrasena
     * Solo actualiza los campos que vienen en el request (no pisa lo que no se manda).
     */
    public function update(Request $request, string $id)
    {
        $cliente = Cliente::find($id);

        if (!$cliente) {
            return response()->json(['error' => 'Cliente no encontrado'], 404);
        }

        // Construimos el array solo con los campos que vienen en el request
        $datos = array_filter([
            'nombre'         => $request->input('nombre'),
            'apellido'       => $request->input('apellido'),
            'correo'         => $request->input('correo'),
            'numero_celular' => $request->input('numero_celular'),
        ], fn($v) => !is_null($v));

        // Si viene contraseña la hasheamos antes de guardar
        if ($request->filled('contrasena')) {
            $datos['contrasena'] = Hash::make($request->input('contrasena'));
        }

        if (empty($datos)) {
            return response()->json(['error' => 'No se enviaron datos para actualizar'], 422);
        }

        $cliente->update($datos);

        return response()->json([
            'success' => true,
            'message' => 'Perfil actualizado correctamente',
            'data'    => $cliente->fresh(), // devuelve los datos actualizados desde la DB
        ]);
    }

    public function destroy(string $idCliente)
    {
        $cliente = Cliente::find($idCliente);
        if (!$cliente) {
            return response()->json(['error' => 'Cliente no encontrado'], 404);
        }
        $cliente->delete();
        return response()->json(['message' => 'Cliente eliminado']);
    }

    public function filtro(Request $request)
    {
        $cliente = Cliente::select(['nombre', 'apellido']);
        if ($request->apellido != null) {
            $cliente = $cliente->where('apellido', $request->apellido);
        }
        if ($request->nombre != null) {
            $cliente = $cliente->where('nombre', $request->nombre);
        }
        if ($request->id_cliente != null) {
            $cliente = $cliente->where('id_cliente', $request->id_cliente[0], $request->id_cliente[1]);
        }
        $cliente = $cliente->orderBy('apellido');
        return ["cantidad" => $cliente->count()];
    }
        
    public function buscarConDispositivos(Request $request)
    {
        $q = trim($request->input('q', ''));
     
        if (strlen($q) < 3) {
            return response()->json([
                'success' => false,
                'message' => 'Ingresá al menos 3 caracteres para buscar'
            ], 422);
        }
     
        $cliente = Cliente::where('numero_celular', $q)
            ->orWhere('correo', $q)
            ->orWhere('nombre', 'like', "%{$q}%")
            ->orWhere('apellido', 'like', "%{$q}%")
            ->with([
                'dispositivos:id_dispositivo,id_cliente,id_modelo,imei,codigo_interno',
                'dispositivos.modelo:id_modelo,nombre_modelo,id_marca',
                'dispositivos.modelo.marca:id_marca,marca',
            ])
            ->first();
     
        if (!$cliente) {
            return response()->json([
                'success' => false,
                'message' => 'Cliente no encontrado'
            ], 404);
        }
     
        return response()->json([
            'success' => true,
            'data'    => [
                'id_cliente'     => $cliente->id_cliente,
                'nombre'         => $cliente->nombre,
                'apellido'       => $cliente->apellido,
                'correo'         => $cliente->correo,
                'numero_celular' => $cliente->numero_celular,
                'dispositivos'   => $cliente->dispositivos,
            ]
        ]);
    }

    public function ultimoCliente()
    {
        $ultimoCliente = Cliente::latest('id_cliente')->first();
        if (!$ultimoCliente) {
            return response()->json(['error' => 'No hay clientes'], 404);
        }
        return response()->json($ultimoCliente);
    }

    public function misDispositivos(Request $request)
    {
        try {
            $cliente = $this->getClienteAutenticado();

            if (!$cliente) {
                return response()->json(['success' => false, 'message' => 'Cliente no encontrado'], 404);
            }

            $dispositivos = Dispositivo::where('id_cliente', $cliente->id_cliente)
                ->with([
                    'modelo:id_modelo,nombre_modelo,id_marca',
                    'modelo.marca:id_marca,marca'
                ])
                ->get();

            $dispositivos = $dispositivos->map(function ($dispositivo) {
                $dispositivo->estado_actual = $this->determinarEstadoDispositivo($dispositivo->id_dispositivo);
                return $dispositivo;
            });

            return response()->json([
                'success' => true,
                'data'    => $dispositivos,
                'total'   => $dispositivos->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener dispositivos',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function detalleDispositivo(Request $request, $idDispositivo)
    {
        try {
             $cliente = $this->getClienteAutenticado();
            $dispositivo = Dispositivo::where('id_dispositivo', $idDispositivo)
                ->where('id_cliente', $cliente->id_cliente)
                ->with([
                    'modelo:id_modelo,nombre_modelo,id_marca',
                    'modelo.marca:id_marca,marca',
                    'ingresos' => function ($query) {
                        $query->latest('fecha_ingreso')->limit(1);
                    },
                    'ingresos.diagnosticos',
                    'ingresos.reparaciones'
                ])
                ->first();

            if (!$dispositivo) {
                return response()->json(['success' => false, 'message' => 'Dispositivo no encontrado'], 404);
            }

            return response()->json(['success' => true, 'data' => $dispositivo]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener dispositivo',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    //--------------------------------clienteautenticado-------------------------------------- 
    //----------------------------------------------------------------------------------------
          private function getClienteAutenticado()
    {
        try {
            $payload = JWTAuth::parseToken()->getPayload();
            $clienteId = $payload->get('sub');
            return Cliente::find($clienteId);
        } catch (\Exception $e) {
            Log::error('Error al obtener cliente autenticado: ' . $e->getMessage());
            return null;
        }
    }


    private function determinarEstadoDispositivo($idDispositivo)
    {
        return 'PENDIENTE';
    }
public function historialReparaciones(Request $request): JsonResponse
{
    try {
        $cliente = $this->getClienteAutenticado();
        if (!$cliente) {
            return response()->json(['success' => false, 'message' => 'No autenticado'], 401);
        }

        $dispositivos = Dispositivo::with([
            'modelo.marca',
            'ingresos' => function ($q) {
                $q->with([
                    'diagnosticos.reparacion' => function ($q) {
                        $q->with([
                            'tecnico:id_usuario,nombre,apellido',
                            'reparacionesMultiples.pieza:id_pieza,nombre_pieza,precio',
                        ]);
                    },
                    'diagnosticos.piezas',
                    'reparacion' => function ($q) {
                        $q->whereNull('id_diagnostico')
                          ->with([
                            'tecnico:id_usuario,nombre,apellido',
                            'reparacionesMultiples.pieza:id_pieza,nombre_pieza,precio',
                          ]);
                    }
                ]);
            }
        ])
        ->where('id_cliente', $cliente->id_cliente)
        ->get();

        $historial = [];

        foreach ($dispositivos as $disp) {
            foreach ($disp->ingresos as $ingreso) {

                // ============================================
                // REPARACIONES CON DIAGNÓSTICO
                // ============================================
                foreach ($ingreso->diagnosticos as $diag) {
                    if ($diag->reparacion) {
                        $piezas = $diag->reparacion->reparacionesMultiples ?? collect([]);
                        
                        $costoDiagnostico = $diag->costo_diagnostico ?? $diag->costo ?? 0;
                        $costoPiezas = $piezas->sum('precio_total');
                        $costoTotal = $costoDiagnostico + $costoPiezas;
                        
                        $historial[] = [
                            'id'                     => $diag->reparacion->id_reparacion,
                            'id_diagnostico'         => $diag->id_diagnostico,
                            'tipo'                   => 'con_diagnostico',
                            'fecha'                  => $piezas->max('fecha_fin_reparacion') 
                                                        ?? $diag->reparacion->fecha_fin 
                                                        ?? $diag->reparacion->created_at 
                                                        ?? $ingreso->fecha_ingreso,
                            'dispositivo'            => $disp->modelo?->nombre_modelo ?? 'Dispositivo',
                            'marca'                  => $disp->modelo?->marca?->marca ?? null,
                            'codigo_interno'         => $disp->codigo_interno,
                            'descripcion'            => $diag->reparacion->comentario 
                                                        ?? $diag->observacion 
                                                        ?? 'Reparación con diagnóstico',
                            'causa_detectada'        => $diag->causa_detectada,
                            'solucion'               => $diag->solucion,
                            'estado'                 => $diag->reparacion->estado_general ?? $diag->estado,
                            'estado_general'         => $diag->reparacion->estado_general,
                            'costo_diagnostico'      => $costoDiagnostico,
                            'costo_total'            => $costoTotal,
                            'reparaciones_multiples' => $piezas->map(function($rm) use ($diag) {
                                $comentarioDiagnostico = $diag->piezas
                                    ?->firstWhere('id_pieza', $rm->id_pieza)
                                    ?->pivot
                                    ?->comentario;

                                return [
                                    'id_multiple'            => $rm->id_multiple,
                                    'id_pieza'               => $rm->id_pieza,
                                    'nombre_pieza'           => $rm->pieza?->nombre_pieza,
                                    'precio_pieza'           => $rm->precio_pieza_momento,
                                    'mano_obra'              => $rm->mano_obra_momento,
                                    'precio_total'           => $rm->precio_total,
                                    'estado'                 => $rm->estado,
                                    'estado_pago'            => $rm->estado_pago ?? 'PENDIENTE',
                                    'comentario_tecnico'     => $rm->comentario_tecnico,
                                    'comentario_diagnostico' => $comentarioDiagnostico,
                                    'fecha_fin'              => $rm->fecha_fin_reparacion,
                                ];
                            })->values()->toArray(),
                            'tecnico'                => $diag->reparacion->tecnico?->nombre 
                                                        ?? $diag->reparacion->tecnico?->apellido 
                                                            ? $diag->reparacion->tecnico->nombre . ' ' . $diag->reparacion->tecnico->apellido 
                                                            : null,
                        ];
                    }
                }

                // ============================================
                // REPARACIONES DIRECTAS (SIN DIAGNÓSTICO)
                // ✅ CORREGIDO: Manejar cuando $ingreso->reparacion es null o un objeto
                // ============================================
                if ($ingreso->reparacion) {
                    $reparaciones = $ingreso->reparacion;
                    
                    // Si es una colección, la usamos directamente; si no, la envolvemos
                    if ($reparaciones instanceof \Illuminate\Database\Eloquent\Collection) {
                        $listaReparaciones = $reparaciones;
                    } else {
                        $listaReparaciones = collect([$reparaciones]);
                    }
                    
                    foreach ($listaReparaciones as $rep) {
                        $piezas = $rep->reparacionesMultiples ?? collect([]);
                        $costoTotal = $piezas->sum('precio_total');
                        
                        $historial[] = [
                            'id'                     => $rep->id_reparacion,
                            'id_diagnostico'         => null,
                            'tipo'                   => 'sin_diagnostico',
                            'fecha'                  => $piezas->max('fecha_fin_reparacion') 
                                                        ?? $rep->created_at 
                                                        ?? $ingreso->fecha_ingreso,
                            'dispositivo'            => $disp->modelo?->nombre_modelo ?? 'Dispositivo',
                            'marca'                  => $disp->modelo?->marca?->marca ?? null,
                            'codigo_interno'         => $disp->codigo_interno,
                            'descripcion'            => $rep->comentario ?? 'Reparación directa',
                            'causa_detectada'        => null,
                            'solucion'               => null,
                            'estado'                 => $rep->estado_general,
                            'estado_general'         => $rep->estado_general,
                            'costo_diagnostico'      => 0,
                            'costo_total'            => $costoTotal,
                            'reparaciones_multiples' => $piezas->map(function($rm) {
                                return [
                                    'id_multiple'            => $rm->id_multiple,
                                    'id_pieza'               => $rm->id_pieza,
                                    'nombre_pieza'           => $rm->pieza?->nombre_pieza,
                                    'precio_pieza'           => $rm->precio_pieza_momento,
                                    'mano_obra'              => $rm->mano_obra_momento,
                                    'precio_total'           => $rm->precio_total,
                                    'estado'                 => $rm->estado,
                                    'estado_pago'            => $rm->estado_pago ?? 'PENDIENTE',
                                    'comentario_tecnico'     => $rm->comentario_tecnico,
                                    'comentario_diagnostico' => null,
                                    'fecha_fin'              => $rm->fecha_fin_reparacion,
                                ];
                            })->values()->toArray(),
                            'tecnico'                => $rep->tecnico?->nombre 
                                                        ?? $rep->tecnico?->apellido 
                                                            ? $rep->tecnico->nombre . ' ' . $rep->tecnico->apellido 
                                                            : null,
                        ];
                    }
                }
            }
        }

        // Ordenar por fecha descendente
        usort($historial, function($a, $b) {
            return strtotime($b['fecha'] ?? 'now') - strtotime($a['fecha'] ?? 'now');
        });

        return response()->json([
            'success' => true,
            'data' => $historial,
            'total' => count($historial)
        ]);

    } catch (\Exception $e) {
        \Log::error('Error en historialReparaciones: ' . $e->getMessage(), [
            'line' => $e->getLine(),
            'file' => $e->getFile()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Error al obtener el historial: ' . $e->getMessage()
        ], 500);
    }
}
}