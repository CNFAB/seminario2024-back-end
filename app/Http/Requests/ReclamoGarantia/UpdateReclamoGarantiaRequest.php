<?php

namespace App\Http\Requests\ReclamoGarantia;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReclamoGarantiaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = auth('usuario')->user();
        
        if (!$user) {
            return false;
        }
        
        // ✅ Usar los nombres correctos de los campos/atributos
        // Suponiendo que tu tabla tiene columnas 'es_administrador' y 'es_tecnico'
        if ($user->es_administrador == true || $user->es_tecnico == true) {
            return true;
        }
        
        return false;
    }

    public function rules(): array
    {
        return [
            'estado' => ['sometimes', 'required', Rule::in(['PENDIENTE', 'EN_REVISION', 'APROBADO', 'RECHAZADO', 'COMPLETADO'])],
            'diagnostico_tecnico' => 'nullable|string|max:500',
            'diagnostico_categoria' => 'nullable|string|max:100',
            'comentario_tecnico' => 'nullable|string|max:500',
            'prioridad' => ['sometimes', 'required', Rule::in(['BAJA', 'MEDIA', 'ALTA', 'URGENTE'])],
            'monto_aprobado' => 'nullable|numeric|min:0|max:999999.99',
            'fecha_resolucion' => 'nullable|date|after_or_equal:fecha_reclamo'
        ];
    }

    public function messages(): array
    {
        return [
            'estado.in' => 'Estado no válido',
            'prioridad.in' => 'Prioridad no válida',
            'monto_aprobado.numeric' => 'El monto debe ser un número válido',
            'fecha_resolucion.after_or_equal' => 'La fecha de resolución debe ser posterior al reclamo'
        ];
    }
}