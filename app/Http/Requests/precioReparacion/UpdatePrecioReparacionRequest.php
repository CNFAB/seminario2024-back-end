<?php

namespace App\Http\Requests\PrecioReparacion;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePrecioReparacionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'descripcion' => 'sometimes|string|max:500',
            'costo_de_reparacion' => 'sometimes|numeric|min:0|max:9999999.99',
            'id_categoria' => 'sometimes|integer|exists:categoria,id_categoria',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'descripcion.max' => 'La descripción es demasiado larga.',
            'costo_de_reparacion.numeric' => 'El costo debe ser un número.',
            'costo_de_reparacion.min' => 'El costo no puede ser negativo.',
            'id_categoria.exists' => 'La categoría no existe.',
        ];
    }
}