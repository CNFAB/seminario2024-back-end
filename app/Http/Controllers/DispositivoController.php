<?php

namespace App\Http\Controllers;

use App\Http\Requests\Dispositivo\StoreDispositivoRequest;
use App\Http\Requests\Dispositivo\UpdateDispositivoRequest;
use App\Models\Cliente;
use App\Models\Dispositivo;
use App\Models\Modelo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class DispositivoController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            // Cargar relaciones para información completa
            $dispositivos = Dispositivo::with(['cliente', 'modelo.marca'])->get();
            
            return response()->json([
                'success' => true,
                'data' => $dispositivos
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los dispositivos'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(StoreDispositivoRequest $request): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            $validated = $request->validated();
            
            //  Buscar por ID, no por correo/celular
            $cliente = Cliente::find($validated['id_cliente']);
            
            //   Buscar por ID, no por nombre
            $modelo = Modelo::find($validated['id_modelo']);

            if (!$cliente) {
                return response()->json([
                    'success' => false,
                    'message' => 'El cliente no existe',
                ], Response::HTTP_NOT_FOUND);
            }

            if (!$modelo) {
                return response()->json([
                    'success' => false,
                    'message' => 'El modelo no existe'
                ], Response::HTTP_NOT_FOUND);
            }

            // Crear el dispositivo
            $dispositivo = $cliente->dispositivos()->create([
                'id_modelo' => $modelo->id_modelo,
                'imei' => $validated['imei'] ?? null,
                'codigo_interno' => $validated['codigo_interno'] ?? null
            ]);

            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Dispositivo creado exitosamente',
                'data' => $dispositivo->load(['cliente', 'modelo.marca'])
            ], Response::HTTP_CREATED);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el dispositivo',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $dispositivo = Dispositivo::with(['cliente', 'modelo.marca'])->find($id);
            
            if (!$dispositivo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dispositivo no encontrado'
                ], Response::HTTP_NOT_FOUND);
            }
            
            return response()->json([
                'success' => true,
                'data' => $dispositivo
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el dispositivo'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(UpdateDispositivoRequest $request, string $id): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            $dispositivo = Dispositivo::find($id);
            
            if (!$dispositivo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dispositivo no encontrado'
                ], Response::HTTP_NOT_FOUND);
            }

            $validated = $request->validated();
            
            // Si se está actualizando el cliente, verificar que exista
            if (isset($validated['id_cliente'])) {
                $cliente = Cliente::find($validated['id_cliente']);
                if (!$cliente) {
                    return response()->json([
                        'success' => false,
                        'message' => 'El cliente no existe'
                    ], Response::HTTP_NOT_FOUND);
                }
            }

            // Si se está actualizando el modelo, verificar que exista
            if (isset($validated['id_modelo'])) {
                $modelo = Modelo::find($validated['id_modelo']);
                if (!$modelo) {
                    return response()->json([
                        'success' => false,
                        'message' => 'El modelo no existe'
                    ], Response::HTTP_NOT_FOUND);
                }
            }

            $dispositivo->update($validated);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Dispositivo actualizado exitosamente',
                'data' => $dispositivo->fresh(['cliente', 'modelo.marca'])
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el dispositivo'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function ultimoDispositivo()
    {
        $ultimoDispositivo = Dispositivo::orderBy('id_dispositivo', 'desc')->first();
        
        if (!$ultimoDispositivo) {
            return response()->json([
                'success' => false,
                'message' => 'No hay dispositivos registrados'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'id_dispositivo' => $ultimoDispositivo->id_dispositivo,
            'imei' => $ultimoDispositivo->imei,
            'id_cliente' => $ultimoDispositivo->id_cliente
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            $dispositivo = Dispositivo::find($id);
            
            if (!$dispositivo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dispositivo no encontrado'
                ], Response::HTTP_NOT_FOUND);
            }

            $dispositivo->delete();
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Dispositivo eliminado exitosamente'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el dispositivo'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function buscarPorCliente(string $correoOCelular): JsonResponse
    {
        try {
            $cliente = Cliente::where('correo', $correoOCelular)
                ->orWhere('numero_celular', $correoOCelular)
                ->first();

            if (!$cliente) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cliente no encontrado'
                ], Response::HTTP_NOT_FOUND);
            }

            $dispositivos = $cliente->dispositivos()->with(['modelo.marca'])->get();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'cliente' => $cliente,
                    'dispositivos' => $dispositivos
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error en la búsqueda'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // Buscar por IMEI
    public function buscarPorImei(string $imei): JsonResponse
    {
        try {
            $dispositivo = Dispositivo::with(['cliente', 'modelo.marca'])
                ->where('imei', $imei)
                ->first();
            
            if (!$dispositivo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dispositivo no encontrado'
                ], Response::HTTP_NOT_FOUND);
            }
            
            return response()->json([
                'success' => true,
                'data' => $dispositivo
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error en la búsqueda'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // Buscar por código interno
    public function buscarPorCodigoInterno(string $codigo): JsonResponse
    {
        try {
            $dispositivo = Dispositivo::with(['cliente', 'modelo.marca'])
                ->where('codigo_interno', $codigo)
                ->first();
            
            if (!$dispositivo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dispositivo no encontrado'
                ], Response::HTTP_NOT_FOUND);
            }
            
            return response()->json([
                'success' => true,
                'data' => $dispositivo
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error en la búsqueda'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}