<?php

namespace App\Http\Requests\diagnosticoPieza;

use Illuminate\Foundation\Http\FormRequest;

class StoreDiagnosticoPiezaRequest extends FormRequest
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
            'id_diagnostico' => 'required|exists:diagnostico,id_diagnostico',
            'id_pieza' => 'required|exists:pieza,id_pieza',
            'estado' => 'nullable|string|in:pendiente,aprobado,rechazado',
            'comentario' => 'nullable|string|max:500',
        ];
    }

    /**
     * Custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'id_diagnostico.required' => 'El diagnóstico es requerido',
            'id_diagnostico.exists' => 'El diagnóstico no existe',
            'id_pieza.required' => 'La pieza es requerida',
            'id_pieza.exists' => 'La pieza no existe',
            'estado.in' => 'El estado debe ser: pendiente, aprobado o rechazado',
            'comentario.max' => 'El comentario no puede superar los 500 caracteres',
        ];
    }

    /**
     * Prepare data after validation with default values.
     */
    public function validatedWithDefaults(): array
    {
        $validated = $this->validated();
        
        // Asignar valores por defecto
        $validated['estado'] = $validated['estado'] ?? 'pendiente';
        $validated['comentario'] = $validated['comentario'] ?? 'Sin comentario';
        $validated['creacion'] = now();
        
        return $validated;
    }
}