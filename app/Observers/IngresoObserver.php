<?php

namespace App\Observers;

use App\Models\Ingreso_d;
use App\Models\Diagnostico;
use App\Models\Usuario;
use Illuminate\Support\Facades\Log;

class IngresoObserver
{
    public function created(Ingreso_d $ingreso): void
    {
        Log::info('=== OBSERVER INICIADO === Para ingreso ID: ' . $ingreso->id_ingreso);
        Log::info('Datos del ingreso:', [
            'revision_tecnica' => $ingreso->revision_tecnica,
            'id_dispositivo' => $ingreso->id_dispositivo,
            'id_usuario' => $ingreso->id_usuario,
            'fecha_ingreso' => $ingreso->fecha_ingreso // ✅ Verificar que existe
        ]);
        
        // Usar revision_tecnica para decidir
        if (!$ingreso->revision_tecnica) {
            Log::info('Observer: Ingreso ID ' . $ingreso->id_ingreso . ' - NO se crea diagnóstico (revision_tecnica = false)');
            return;
        }
        
        Log::info('Observer: Creando diagnóstico para ingreso ID ' . $ingreso->id_ingreso . ' (revision_tecnica = true)');

        try {
            // 1. Verificar que no tenga ya un diagnóstico
            $diagnosticoExistente = Diagnostico::where('id_ingreso', $ingreso->id_ingreso)->first();
            if ($diagnosticoExistente) {
                Log::warning('Observer: El ingreso ID ' . $ingreso->id_ingreso . ' ya tiene un diagnóstico');
                return;
            }
            
            // 2. Buscar técnico con menor carga
            $tecnico = $this->tecnicoDeMenorCarga();

            if (!$tecnico) {
                Log::error('Observer: No hay técnicos disponibles para asignar el diagnóstico al ingreso ' . $ingreso->id_ingreso);
                return;
            }
            
            // ✅ 3. Cargar relación de usuario para obtener información del cliente
            $clienteInfo = '';
            $ingreso->load('usuario'); // Asegurar que la relación esté cargada
            
            if ($ingreso->usuario) {
                $clienteInfo = 'Cliente: ' . $ingreso->usuario->nombre . ' ' . $ingreso->usuario->apellido;
                Log::info('Observer: Cliente encontrado: ' . $clienteInfo);
            } else {
                Log::warning('Observer: No se encontró usuario para ingreso ID ' . $ingreso->id_ingreso);
                $clienteInfo = 'Cliente: No especificado';
            }
            
            // 4. Crear diagnóstico automático
            $diagnostico = Diagnostico::create([
                'id_ingreso' => $ingreso->id_ingreso,
                'id_usuario' => $tecnico->id_usuario,
                'observacion' => 'Diagnóstico creado automáticamente tras ingreso. ' . $clienteInfo,
                'costo' => 0.00,
                'costo_reparacion' => 0.00,
                'estado' => 'ESPERANDO_DIAGNOSTICO',
                'gravedad' => 'LEVE',
                'causa_detectada' => 'Análisis pendiente',
                'solucion' => 'Por determinar',
                'fecha_expiracion' => now()->addDays(7)->toDateString(),
                'id_precio_r' => null
            ]);
            
            Log::info('Observer: Diagnóstico creado exitosamente', [
                'diagnostico_id' => $diagnostico->id_diagnostico,
                'ingreso_id' => $ingreso->id_ingreso,
                'tecnico_id' => $tecnico->id_usuario,
                'tecnico_nombre' => $tecnico->nombre . ' ' . $tecnico->apellido,
                'fecha_ingreso_verificada' => $ingreso->fecha_ingreso
            ]);

        } catch (\Exception $e) {
            Log::error('Observer: Error al crear diagnóstico para ingreso ID ' . $ingreso->id_ingreso, [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    private function tecnicoDeMenorCarga(): ?Usuario
    {
        try {
            Log::info('Observer: Buscando técnico con menor carga...');
            
            $tecnico = Usuario::where('es_tecnico', true)
                ->select('id_usuario', 'nombre', 'apellido', 'correo') // ✅ Cambiado 'email' a 'correo'
                ->withCount(['diagnosticos' => function($query) {
                    $query->whereIn('estado', ['ESPERANDO_DIAGNOSTICO', 'EN_REPARACION']);
                }])
                ->orderBy('diagnosticos_count', 'asc')
                ->first();
            
            if ($tecnico) {
                Log::info('Observer: Técnico encontrado', [
                    'id' => $tecnico->id_usuario,
                    'nombre' => $tecnico->nombre . ' ' . $tecnico->apellido,
                    'carga' => $tecnico->diagnosticos_count
                ]);
            } else {
                Log::warning('Observer: No se encontraron técnicos disponibles');
            }
            
            return $tecnico;

        } catch (\Exception $e) {
            Log::error('Observer: Error al buscar técnico: ' . $e->getMessage());
            return null;
        }
    }
}