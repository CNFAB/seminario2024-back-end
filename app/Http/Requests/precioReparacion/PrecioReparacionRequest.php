<?php

namespace App\Http\Requests\PrecioReparacion;

use Illuminate\Foundation\Http\FormRequest;

class PrecioReparacionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Cambia a false si necesitas lógica de autorización
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Reglas comunes para store y update
        $rules = [
            'descripcion' => 'required|string|max:500',
            'costo_de_reparacion' => 'required|numeric|min:0|max:9999999.99',
            'id_categoria' => 'required|exists:categoria,id_categoria',
        ];

        // Si es una actualización, hacemos que algunos campos sean opcionales
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules = [
                'descripcion' => 'sometimes|string|max:500',
                'costo_de_reparacion' => 'sometimes|numeric|min:0|max:9999999.99',
                'id_categoria' => 'sometimes|exists:categoria,id_categoria',
            ];
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'descripcion.required' => 'La descripción es obligatoria.',
            'descripcion.string' => 'La descripción debe ser un texto válido.',
            'descripcion.max' => 'La descripción no puede exceder los 500 caracteres.',
            
            'costo_de_reparacion.required' => 'El costo de reparación es obligatorio.',
            'costo_de_reparacion.numeric' => 'El costo de reparación debe ser un número válido.',
            'costo_de_reparacion.min' => 'El costo de reparación no puede ser negativo.',
            'costo_de_reparacion.max' => 'El costo de reparación no puede exceder los 9,999,999.99.',
            
            'id_categoria.required' => 'La categoría es obligatoria.',
            'id_categoria.exists' => 'La categoría seleccionada no existe en el sistema.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'descripcion' => 'descripción',
            'costo_de_reparacion' => 'costo de reparación',
            'id_categoria' => 'categoría',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Puedes transformar los datos antes de validar si es necesario
        if ($this->has('costo_de_reparacion')) {
            $this->merge([
                'costo_de_reparacion' => (float) str_replace(',', '', $this->costo_de_reparacion),
            ]);
        }
    }
}