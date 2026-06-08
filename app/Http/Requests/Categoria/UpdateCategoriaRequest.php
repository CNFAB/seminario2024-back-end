<?php

namespace App\Http\Requests\Categoria;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Obtiene el ID de la categoría desde la ruta
        $categoriaId = $this->route('id');
        
        return [
            'categoria' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('categoria', 'categoria')->ignore($categoriaId, 'id_categoria')
            ],
            'mano_obra' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
                'max:999.99',
                'regex:/^\d+(\.\d{1,2})?$/'
            ],
            // 👇 NUEVO CAMPO GARANTIA_DIAS
            'garantia_dias' => [
                'sometimes',           // Solo si viene en la petición
                'nullable',
                'integer',
                'min:0',
                'max:1095'             // Máximo 3 años
            ]
        ];
    }

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

    public function attributes(): array
    {
        return [
            'categoria' => 'categoría',
            'mano_obra' => 'mano de obra',
            'garantia_dias' => 'días de garantía'  // 👈 NUEVO
        ];
    }

    protected function prepareForValidation(): void
    {
        // Limpiar categoria si viene
        if ($this->has('categoria')) {
            $this->merge(['categoria' => trim($this->categoria)]);
        }
        
        // Limpiar mano_obra si viene
        if ($this->has('mano_obra')) {
            $this->merge(['mano_obra' => $this->mano_obra ?: 0.00]);
        }
        
        // 👇 Limpiar garantia_dias si viene
        if ($this->has('garantia_dias')) {
            // Si es null o vacío, lo dejamos como null (no se actualiza)
            // Si tiene valor, lo convertimos a entero
            $valor = $this->garantia_dias;
            if ($valor !== null && $valor !== '') {
                $this->merge(['garantia_dias' => (int) $valor]);
            }
        }
    }

    /**
     * Opcional: Validación después de pasar las reglas
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Verificar que al menos un campo viene para actualizar
            if (!$this->has('categoria') && !$this->has('mano_obra') && !$this->has('garantia_dias')) {
                $validator->errors()->add(
                    'campos',
                    'Debe enviar al menos un campo para actualizar: categoria, mano_obra o garantia_dias'
                );
            }
        });
    }
}