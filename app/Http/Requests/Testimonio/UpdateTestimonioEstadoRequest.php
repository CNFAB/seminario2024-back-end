<?php

namespace App\Http\Requests\Testimonio;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTestimonioEstadoRequest extends FormRequest
{
    /**
     * Determinar si el usuario está autorizado
     * Solo administradores pueden moderar testimonios
     */
  public function authorize(): bool
{
    $user = auth('api')->user() ?? auth('usuario')->user();
    
    if (!$user) return false;
    
    return $user->es_administrador || $user->es_tecnico;
}
    /**
     * Reglas de validación
     */
    public function rules(): array
    {
        return [
            'estado' => 'required|string|in:PENDIENTE,APROBADO,RECHAZADO',
        ];
    }

    /**
     * Mensajes de error personalizados
     */
    public function messages(): array
    {
        return [
            'estado.required' => 'Debes seleccionar un estado.',
            'estado.in' => 'El estado debe ser PENDIENTE, APROBADO o RECHAZADO.',
        ];
    }
}