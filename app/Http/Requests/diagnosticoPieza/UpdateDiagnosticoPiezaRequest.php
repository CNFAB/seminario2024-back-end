<?php

namespace App\Http\Requests\diagnosticoPieza;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDiagnosticoPiezaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Cambiar a true para permitir, o ajustar según lógica de permisos
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Todos los campos son opcionales para permitir actualización parcial
            'id_pieza' => 'sometimes|exists:pieza,id_pieza',
            'estado' => 'sometimes|string|in:pendiente,aprobado,rechazado',
            'comentario' => 'sometimes|nullable|string|max:500',
            'fecha_aprobacion' => 'sometimes|nullable|date',
            'fecha_rechazo' => 'sometimes|nullable|date',
        ];
    }

    /**
     * Custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'id_pieza.exists' => 'La pieza no existe',
            'estado.in' => 'El estado debe ser: pendiente, aprobado o rechazado',
            'comentario.max' => 'El comentario no puede superar los 500 caracteres',
            'fecha_aprobacion.date' => 'La fecha de aprobación debe ser una fecha válida',
            'fecha_rechazo.date' => 'La fecha de rechazo debe ser una fecha válida',
        ];
    }

    /**
     * Prepare data for update - only returns fields that were actually sent.
     */
    public function getUpdateData(): array
    {
        $data = [];
        
        // Solo incluir campos que están presentes en la request
        if ($this->has('id_pieza')) {
            $data['id_pieza'] = $this->id_pieza;
        }
        
        if ($this->has('estado')) {
            $data['estado'] = $this->estado;
            
            // Lógica automática para fechas según el estado
            if ($this->estado === 'aprobado') {
                $data['fecha_aprobacion'] = $this->fecha_aprobacion ?? now();
                $data['fecha_rechazo'] = null;
            } elseif ($this->estado === 'rechazado') {
                $data['fecha_rechazo'] = $this->fecha_rechazo ?? now();
                $data['fecha_aprobacion'] = null;
            } elseif ($this->estado === 'pendiente') {
                $data['fecha_aprobacion'] = null;
                $data['fecha_rechazo'] = null;
            }
        }
        
        // Actualizar comentario (si se envía)
        if ($this->has('comentario')) {
            $data['comentario'] = $this->comentario ?: 'Sin comentario';
        }
        
        // Actualizar fechas manualmente si se enviaron explícitamente
        if ($this->has('fecha_aprobacion')) {
            $data['fecha_aprobacion'] = $this->fecha_aprobacion;
        }
        
        if ($this->has('fecha_rechazo')) {
            $data['fecha_rechazo'] = $this->fecha_rechazo;
        }
        
        return $data;
    }

    /**
     * Check if at least one field is being updated.
     */
    public function hasAtLeastOneField(): bool
    {
        $fields = ['id_pieza', 'estado', 'comentario', 'fecha_aprobacion', 'fecha_rechazo'];
        
        foreach ($fields as $field) {
            if ($this->has($field)) {
                return true;
            }
        }
        
        return false;
    }
}