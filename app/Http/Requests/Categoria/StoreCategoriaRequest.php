<?php

namespace App\Http\Requests\Categoria;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoriaRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para hacer esta solicitud.
     */
    public function authorize(): bool
    {
        return true; // Cambia a false si necesitas lógica de autorización
    }

    /**
     * Obtiene las reglas de validación que se aplican a la solicitud.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'categoria' => [
                'required',
                'string',
                'max:100',
                Rule::unique('categoria', 'categoria')
            ],
            'mano_obra' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999.99',
                'regex:/^\d+(\.\d{1,2})?$/'
            ],
            // 👇 NUEVO CAMPO GARANTIA_DIAS
            'garantia_dias' => [
                'nullable',
                'integer',
                'min:0',
                'max:1095',  // Máximo 3 años (1095 días)
            ]
        ];
    }

    /**
     * Mensajes de error personalizados.
     */
    public function messages(): array
    {
        return [
            'categoria.required' => 'El nombre de la categoría es obligatorio.',
            'categoria.string' => 'El nombre debe ser texto.',
            'categoria.max' => 'El nombre no puede exceder los 100 caracteres.',
            'categoria.unique' => 'Esta categoría ya existe en el sistema.',
            'mano_obra.numeric' => 'El valor de mano de obra debe ser numérico.',
            'mano_obra.min' => 'El valor de mano de obra no puede ser negativo.',
            'mano_obra.max' => 'El valor de mano de obra no puede exceder 999.99.',
            'mano_obra.regex' => 'El valor de mano de obra debe tener máximo 2 decimales.',
            // 👇 NUEVOS MENSAJES
            'garantia_dias.integer' => 'Los días de garantía deben ser un número entero.',
            'garantia_dias.min' => 'Los días de garantía no pueden ser negativos.',
            'garantia_dias.max' => 'Los días de garantía no pueden exceder 1095 días (3 años).'
        ];
    }

    /**
     * Atributos personalizados para los mensajes de error.
     */
    public function attributes(): array
    {
        return [
            'categoria' => 'categoría',
            'mano_obra' => 'mano de obra',
            'garantia_dias' => 'días de garantía'  // 👈 NUEVO
        ];
    }

    /**
     * Prepara los datos para la validación.
     */
    protected function prepareForValidation(): void
    {
        // Limpiar y formatear los datos antes de validar
        $this->merge([
            'categoria' => trim($this->categoria),
            'mano_obra' => $this->mano_obra ?: 0.00,
            // 👇 Si no viene garantia_dias, usar 90 por defecto
            'garantia_dias' => $this->garantia_dias ?: 90
        ]);
    }

    /**
     * Configuración adicional después de la validación.
     */
    protected function passedValidation(): void
    {
        // Aquí puedes hacer algo después de que pase la validación si es necesario
    }
}