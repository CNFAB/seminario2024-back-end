<?php
namespace App\Http\Requests\Diagnostico;

use Illuminate\Foundation\Http\FormRequest;

class CrearAutomaticoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_ingreso' => 'required|integer|exists:ingreso_d,id_ingreso',
            'observacion' => 'nullable|string|max:500',
            'fecha_expiracion' => 'nullable|date|after:today',
               'id_usuario' => 'nullable|integer|exists:usuario,id_usuario',
            'id_pieza' => 'nullable|integer|exists:pieza,id_pieza', 
        ];
    }

    public function messages(): array
    {
        return [
            'id_ingreso.required' => 'El ID de ingreso es obligatorio.',
            'id_ingreso.exists' => 'El ingreso no existe.',
            'fecha_expiracion.after' => 'La fecha de expiración debe ser futura.',
            'id_pieza.exists' => 'La pieza seleccionada no existe.', // ✅ MENSAJE NUEVO
        ];
    }
}