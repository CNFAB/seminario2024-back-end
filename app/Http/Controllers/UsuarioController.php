<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Models\Usuario;
use App\Http\Requests\Usuario\StoreUsuarioRequest;
use App\Http\Requests\Usuario\UpdateUsuarioRequest;

class UsuarioController extends Controller
{
    public function index()
    {
        try {
            $usuarios = Usuario::select([
                'id_usuario',
                'nombre',
                'apellido',
                'correo',
                'numero_celular',
                'es_tecnico',
                'es_recepcionista',
                'es_administrador',
                'activo'
            ])->get();

            return response()->json($usuarios, 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener usuarios',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    public function store(StoreUsuarioRequest $request)
    {
        try {
            $data = $request->validated();
            
            // Asegurar que los campos booleanos tengan valor por defecto
            $data['es_tecnico'] = $data['es_tecnico'] ?? false;
            $data['es_recepcionista'] = $data['es_recepcionista'] ?? false;
            $data['es_administrador'] = $data['es_administrador'] ?? false;

            $usuario = Usuario::create($data);

            return response()->json([
                'success' => true,
                'message' => 'Usuario creado exitosamente',
                'data' => [
                    'id_usuario' => $usuario->id_usuario,
                    'nombre' => $usuario->nombre,
                    'apellido' => $usuario->apellido,
                    'correo' => $usuario->correo,
                    'numero_celular' => $usuario->numero_celular,
                    'es_tecnico' => $usuario->es_tecnico,
                    'es_recepcionista' => $usuario->es_recepcionista,
                    'es_administrador' => $usuario->es_administrador
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el usuario',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $usuario = Usuario::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => [
                    'id_usuario' => $usuario->id_usuario,
                    'nombre' => $usuario->nombre,
                    'apellido' => $usuario->apellido,
                    'correo' => $usuario->correo,
                    'numero_celular' => $usuario->numero_celular,
                    'es_tecnico' => $usuario->es_tecnico,
                    'es_recepcionista' => $usuario->es_recepcionista,
                    'es_administrador' => $usuario->es_administrador,
                    'nombre_completo' => $usuario->nombre_completo
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado'
            ], 404);
        }
    }

    public function update(UpdateUsuarioRequest $request, $id)
    {
        try {
            $usuario = Usuario::findOrFail($id);
            $data = $request->validated();

            // Si la contraseña está vacía o no se envía, eliminarla del array
            if (empty($data['contrasena'])) {
                unset($data['contrasena']);
            }

            $usuario->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Usuario actualizado exitosamente',
                'data' => [
                    'id_usuario' => $usuario->id_usuario,
                    'nombre' => $usuario->nombre,
                    'apellido' => $usuario->apellido,
                    'correo' => $usuario->correo,
                    'numero_celular' => $usuario->numero_celular,
                    'es_tecnico' => $usuario->es_tecnico,
                    'es_recepcionista' => $usuario->es_recepcionista,
                    'es_administrador' => $usuario->es_administrador
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el usuario',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    // UsuarioController.php
// app/Http/Controllers/UsuarioController.php

public function toggleActivo($id)
{
    try {
        \Log::info('Toggle activo llamado para ID: ' . $id);
        
        $usuario = Usuario::findOrFail($id);
        
        $nuevoEstado = !$usuario->activo;
        $usuario->activo = $nuevoEstado;
        $usuario->save();

        return response()->json([
            'success' => true,
            'message' => $nuevoEstado ? 'Usuario activado exitosamente' : 'Usuario desactivado exitosamente',
            'data' => [
                'id_usuario' => $usuario->id_usuario,
                'activo' => $usuario->activo
            ]
        ]);

    } catch (\Exception $e) {
        \Log::error('Error en toggleActivo: ' . $e->getMessage());
        
        return response()->json([
            'success' => false,
            'message' => 'Error al cambiar el estado: ' . $e->getMessage()
        ], 500);
    }
}

    public function destroy($id)
{
    try {
        $usuario = Usuario::findOrFail($id);
        
        $usuario->activo = false;
        $usuario->save();

        return response()->json([
            'success' => true,
            'message' => 'Usuario desactivado exitosamente'
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error al desactivar el usuario',
            'error' => $e->getMessage()
        ], 500);
    }
}

            public function activos()
            {
                $usuarios = Usuario::activos()->get();
                return response()->json($usuarios);
            }



    public function obtenerCargaTrabajoTecnicos()
    {
    try {
        $tecnicos = Usuario::where('es_tecnico', true)
            ->get(['id_usuario', 'nombre', 'apellido', 'correo']);

        $resultado = $tecnicos->map(function ($tecnico) {

            // Contar reparaciones_múltiples activas del técnico
            // Reparacion.id_usuario = técnico → reparacionesMultiples.estado activo
            $cargaActiva = \App\Models\ReparacionMultiple::whereHas('reparacion', function ($q) use ($tecnico) {
                    $q->where('id_usuario', $tecnico->id_usuario);
                })
                ->whereIn('estado', ['PENDIENTE', 'EN_REPARACION', 'ESPERANDO_PIEZA'])
                ->count();

            // Totales históricos
            $totalAsignadas = \App\Models\ReparacionMultiple::whereHas('reparacion', function ($q) use ($tecnico) {
                    $q->where('id_usuario', $tecnico->id_usuario);
                })
                ->count();

            $terminadas = \App\Models\ReparacionMultiple::whereHas('reparacion', function ($q) use ($tecnico) {
                    $q->where('id_usuario', $tecnico->id_usuario);
                })
                ->where('estado', 'TERMINADO')
                ->count();

            return [
                'id_usuario'      => $tecnico->id_usuario,
                'nombre'          => $tecnico->nombre,
                'apellido'        => $tecnico->apellido,
                'correo'          => $tecnico->correo,
                'carga_trabajo'   => $cargaActiva,       // activas ahora
                'total_asignadas' => $totalAsignadas,    // histórico total
                'terminadas'      => $terminadas,        // histórico terminadas
            ];
        });

        return response()->json($resultado, 200);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error al obtener carga de trabajo',
            'error'   => $e->getMessage()
        ], 500);
    }
}

    // ============================================
    // MÉTODOS PARA ROLES ESPECÍFICOS
    // ============================================

    public function tecnicos()
    {
        try {
            $tecnicos = Usuario::where('es_tecnico', true)
                ->get([
                    'id_usuario',
                    'nombre',
                    'apellido',
                    'correo',
                    'numero_celular'
                ]);

            return response()->json($tecnicos, 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener técnicos'
            ], 500);
        }
    }

    public function recepcionistas()
    {
        try {
            $recepcionistas = Usuario::where('es_recepcionista', true)
                ->get([
                    'id_usuario',
                    'nombre',
                    'apellido',
                    'correo',
                    'numero_celular'
                ]);

            return response()->json($recepcionistas, 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener recepcionistas'
            ], 500);
        }
    }

    public function administradores()
    {
        try {
            $administradores = Usuario::where('es_administrador', true)
                ->get([
                    'id_usuario',
                    'nombre',
                    'apellido',
                    'correo',
                    'numero_celular'
                ]);

            return response()->json($administradores, 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener administradores'
            ], 500);
        }
    }

    /**
     * Login de usuario
     */
    public function login(Request $request)
    {
        $request->validate([
            'correo' => 'required|email',
            'contrasena' => 'required|string'
        ]);

        $usuario = Usuario::where('correo', $request->correo)->first();

        if ($usuario && password_verify($request->contrasena, $usuario->contrasena_hash)) {
            return response()->json([
                'success' => true,
                'message' => 'Login exitoso',
                'data' => [
                    'id_usuario' => $usuario->id_usuario,
                    'nombre' => $usuario->nombre,
                    'apellido' => $usuario->apellido,
                    'correo' => $usuario->correo,
                    'roles' => [
                        'es_tecnico' => $usuario->es_tecnico,
                        'es_recepcionista' => $usuario->es_recepcionista,
                        'es_administrador' => $usuario->es_administrador
                    ]
                ]
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Credenciales incorrectas'
        ], Response::HTTP_UNAUTHORIZED);
    }
}