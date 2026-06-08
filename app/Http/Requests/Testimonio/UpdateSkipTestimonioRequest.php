<?php

namespace App\Http\Requests\Testimonio;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSkipTestimonioRequest extends FormRequest
{
    /**
     * Determinar si el usuario está autorizado
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Reglas de validación
     */
    public function rules(): array
    {
        return [
            'id_reparacion' => 'required|integer|exists:reparacion,id_reparacion',
            'skip_testimonio' => 'required|boolean',
        ];
    }

    /**
     * Mensajes de error personalizados
     */
    public function messages(): array
    {
        return [
            'id_reparacion.required' => 'Debes seleccionar una reparación.',
            'id_reparacion.exists' => 'La reparación no existe.',
            'skip_testimonio.required' => 'Debes especificar si no quieres volver a preguntar.',
        ];
    }
}