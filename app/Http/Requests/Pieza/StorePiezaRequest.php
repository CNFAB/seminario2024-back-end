<?php

namespace App\Http\Requests\Pieza;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePiezaRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para hacer esta solicitud.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Obtiene las reglas de validación que se aplican a la solicitud.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nombre_pieza' => [
                'required',
                'string',
                'max:255',
                Rule::unique('pieza', 'nombre_pieza')
                ->where(function ($query) {
                    return $query->where('id_categoria', $this->id_categoria);
                })// Asegura que el nombre sea único
            ],
            'stock' => [
                'nullable',
                'integer',
                'min:0',
                'max:32767' // Límite de smallint
            ],
            'precio' => [
                'required',
                'numeric',
                'min:0',
                'max:99999999.99', // Para numeric(10,2)
                'regex:/^\d+(\.\d{1,2})?$/'
            ],
            'id_categoria' => [
                'required',
                'integer',
                'exists:categoria,id_categoria' // Valida que exista la categoría
            ]
        ];
    }

    /**
     * Mensajes de error personalizados.
     */
    public function messages(): array
    {
        return [
            'nombre_pieza.required' => 'El nombre de la pieza es obligatorio.',
            'nombre_pieza.string' => 'El nombre debe ser texto.',
            'nombre_pieza.max' => 'El nombre no puede exceder los 255 caracteres.',
            'nombre_pieza.unique' => 'Esta pieza ya existe en el sistema.',
            
            'stock.integer' => 'El stock debe ser un número entero.',
            'stock.min' => 'El stock no puede ser negativo.',
            'stock.max' => 'El stock no puede exceder 32767 unidades.',
            
            'precio.required' => 'El precio es obligatorio.',
            'precio.numeric' => 'El precio debe ser numérico.',
            'precio.min' => 'El precio no puede ser negativo.',
            'precio.max' => 'El precio no puede exceder 99,999,999.99.',
            'precio.regex' => 'El precio debe tener máximo 2 decimales.',
            
            'id_categoria.required' => 'La categoría es obligatoria.',
            'id_categoria.integer' => 'La categoría debe ser un ID válido.',
            'id_categoria.exists' => 'La categoría seleccionada no existe.'
        ];
    }

    /**
     * Atributos personalizados para los mensajes de error.
     */
    public function attributes(): array
    {
        return [
            'nombre_pieza' => 'nombre de pieza',
            'stock' => 'stock',
            'precio' => 'precio',
            'id_categoria' => 'categoría'
        ];
    }

    /**
     * Prepara los datos para la validación.
     */
    protected function prepareForValidation(): void
    {
        // Limpiar y formatear los datos antes de validar
        $this->merge([
            'nombre_pieza' => trim($this->nombre_pieza),
            'stock' => $this->stock ?? 1, // Valor por defecto si no se envía
            'precio' => $this->precio ?? 0.00,
        ]);
    }
}