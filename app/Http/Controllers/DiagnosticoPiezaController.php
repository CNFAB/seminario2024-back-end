<?php

namespace App\Http\Controllers;


use App\Models\DiagnosticoPieza;
use App\Models\Pieza;
use App\Models\Diagnostico;
use App\Http\Requests\diagnosticoPieza\StoreDiagnosticoPiezaRequest;
use App\Http\Requests\diagnosticoPieza\UpdateDiagnosticoPiezaRequest;
use Illuminate\Http\Request;

class DiagnosticoPiezaController extends Controller
{
    /**
     * Display a listing of all diagnostic pieces.
     */
    public function index()
    {
        $diagnosticosPieza = DiagnosticoPieza::with(['pieza', 'diagnostico'])->get();
        
        return response()->json([
            'data' => $diagnosticosPieza
        ]);
    }

    /**
     * Display diagnostic pieces by diagnostico ID.
     */
    public function indexByDiagnostico($id_diagnostico)
    {
        $diagnosticosPieza = DiagnosticoPieza::with(['pieza'])
            ->where('id_diagnostico', $id_diagnostico)
            ->get();
        
        return response()->json([
            'data' => $diagnosticosPieza
        ]);
    }

    /**
     * Display diagnostic pieces by pieza ID.
     */
    public function indexByPieza($id_pieza)
    {
        $diagnosticosPieza = DiagnosticoPieza::with(['diagnostico'])
            ->where('id_pieza', $id_pieza)
            ->get();
        
        return response()->json([
            'data' => $diagnosticosPieza
        ]);
    }

    /**
     * Store a newly created diagnostic piece.
     */
    public function store(StoreDiagnosticoPiezaRequest $request)
    {
        // Obtener la pieza para su precio
        $pieza = Pieza::findOrFail($request->id_pieza);
        
        // Obtener datos validados con valores por defecto
        $data = $request->validatedWithDefaults();
        
        // Asignar el costo automáticamente desde el precio de la pieza
        $data['costo'] = $pieza->precio;
        
        // Crear el registro
        $diagnosticoPieza = DiagnosticoPieza::create($data);
        
        return response()->json([
            'success' => true,
            'message' => 'Diagnóstico de pieza creado exitosamente',
            'data' => $diagnosticoPieza->load('pieza')
        ], 201);
    }

    /**
     * Display the specified diagnostic piece.
     */
    public function show($id)
    {
        $diagnosticoPieza = DiagnosticoPieza::with(['pieza', 'diagnostico'])->findOrFail($id);
        
        return response()->json([
            'data' => $diagnosticoPieza
        ]);
    }

    /**
     * Update the specified diagnostic piece.
     */
    public function update(UpdateDiagnosticoPiezaRequest $request, $id)
    {
        $diagnosticoPieza = DiagnosticoPieza::findOrFail($id);
        
        // Verificar que se envía al menos un campo para actualizar
        if (!$request->hasAtLeastOneField()) {
            return response()->json([
                'success' => false,
                'message' => 'Debe proporcionar al menos un campo para actualizar',
                'fields_disponibles' => ['id_pieza', 'estado', 'comentario', 'fecha_aprobacion', 'fecha_rechazo']
            ], 422);
        }
        
        // Obtener solo los campos que se enviaron
        $updateData = $request->getUpdateData();
        
        // Si se actualiza la pieza, actualizar también el costo
        if (isset($updateData['id_pieza']) && $updateData['id_pieza'] != $diagnosticoPieza->id_pieza) {
            $nuevaPieza = Pieza::find($updateData['id_pieza']);
            if ($nuevaPieza) {
                $updateData['costo'] = $nuevaPieza->precio;
            }
        }
        
        // Actualizar el registro
        $diagnosticoPieza->update($updateData);
        
        return response()->json([
            'success' => true,
            'message' => 'Diagnóstico de pieza actualizado exitosamente',
            'data' => $diagnosticoPieza->fresh()->load('pieza')
        ]);
    }

    /**
     * Update only the status of a diagnostic piece.
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'estado' => 'required|in:pendiente,aprobado,rechazado',
            'comentario' => 'nullable|string|max:500'
        ]);
        
        $diagnosticoPieza = DiagnosticoPieza::findOrFail($id);
        
        $updateData = ['estado' => $request->estado];
        
        // Manejar fechas según el estado
        if ($request->estado === 'aprobado') {
            $updateData['fecha_aprobacion'] = now();
            $updateData['fecha_rechazo'] = null;
        } elseif ($request->estado === 'rechazado') {
            $updateData['fecha_rechazo'] = now();
            $updateData['fecha_aprobacion'] = null;
        } else {
            $updateData['fecha_aprobacion'] = null;
            $updateData['fecha_rechazo'] = null;
        }
        
        if ($request->has('comentario')) {
            $updateData['comentario'] = $request->comentario ?: 'Sin comentario';
        }
        
        $diagnosticoPieza->update($updateData);
        
        return response()->json([
            'success' => true,
            'message' => 'Estado actualizado correctamente',
            'data' => $diagnosticoPieza->fresh()->load('pieza')
        ]);
    }

    /**
     * Approve a diagnostic piece.
     */
     public function approve($idDiagnosticoPieza)
    {
        try {
            $diagnosticoPieza = DiagnosticoPieza::with('pieza')->findOrFail($idDiagnosticoPieza);
            
            $diagnosticoPieza->update([
                'estado' => 'APROBADO',  // ← MAYÚSCULAS consistente
                'fecha_aprobacion' => now(),
                'fecha_rechazo' => null
            ]);
            
            // Actualizar el costo total en el diagnóstico
            $this->actualizarCostoTotalDiagnostico($diagnosticoPieza->id_diagnostico);
            
            return response()->json([
                'success' => true,
                'message' => 'Pieza aprobada exitosamente',
                'data' => $diagnosticoPieza->fresh()->load('pieza')
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al aprobar la pieza: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject a diagnostic piece.
     */
     public function reject($idDiagnosticoPieza, Request $request)
    {
        try {
            $diagnosticoPieza = DiagnosticoPieza::with('pieza')->findOrFail($idDiagnosticoPieza);
            
            $updateData = [
                'estado' => 'RECHAZADO',  // ← MAYÚSCULAS consistente
                'fecha_rechazo' => now(),
                'fecha_aprobacion' => null
            ];
            
            if ($request->has('comentario')) {
                $updateData['comentario'] = $request->comentario ?: 'pieza creada ';
            }
            
            $diagnosticoPieza->update($updateData);
            
            // Actualizar el costo total en el diagnóstico
            $this->actualizarCostoTotalDiagnostico($diagnosticoPieza->id_diagnostico);
            
            return response()->json([
                'success' => true,
                'message' => 'Pieza rechazada exitosamente',
                'data' => $diagnosticoPieza->fresh()->load('pieza')
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al rechazar la pieza: ' . $e->getMessage()
            ], 500);
        }
    }


    /**
     * Remove the specified diagnostic piece.
     */
    public function destroy($id)
    {
        $diagnosticoPieza = DiagnosticoPieza::findOrFail($id);
        $diagnosticoPieza->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Diagnóstico de pieza eliminado exitosamente'
        ]);
    }

    /**
     * Get total cost by diagnostico ID.
     */
    public function getTotalCostByDiagnostico($id_diagnostico)
    {
        $total = DiagnosticoPieza::where('id_diagnostico', $id_diagnostico)
            ->where('estado', 'aprobado')
            ->sum('costo');
        
        $aprobadas = DiagnosticoPieza::where('id_diagnostico', $id_diagnostico)
            ->where('estado', 'aprobado')
            ->count();
        
        $rechazadas = DiagnosticoPieza::where('id_diagnostico', $id_diagnostico)
            ->where('estado', 'rechazado')
            ->count();
        
        $pendientes = DiagnosticoPieza::where('id_diagnostico', $id_diagnostico)
            ->where('estado', 'pendiente')
            ->count();
        
        return response()->json([
            'data' => [
                'id_diagnostico' => $id_diagnostico,
                'total_piezas' => $aprobadas + $rechazadas + $pendientes,
                'piezas_aprobadas' => $aprobadas,
                'piezas_rechazadas' => $rechazadas,
                'piezas_pendientes' => $pendientes,
                'costo_total' => $total,
                'costo_total_formateado' => '$' . number_format($total, 2)
            ]
        ]);
    }
      private function actualizarCostoTotalDiagnostico($idDiagnostico)
    {
        $total = DiagnosticoPieza::where('id_diagnostico', $idDiagnostico)
            ->where('estado', 'APROBADO')
            ->sum('costo');
        
        Diagnostico::where('id_diagnostico', $idDiagnostico)
            ->update(['costo' => $total]);
    }

}