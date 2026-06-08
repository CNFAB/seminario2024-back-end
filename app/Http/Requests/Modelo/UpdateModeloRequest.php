<?php

namespace App\Http\Requests\Modelo;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateModeloRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $modeloId = $this->route('modelo');

        return [
            'id_marca' => 'sometimes|required|integer|exists:marca,id_marca',
            'nombre_modelo' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                $this->uniqueRule($modeloId)
            ],
            'ram' => 'nullable|integer|min:1|max:128',
            'almacenamiento' => 'nullable|integer|min:1|max:1024',
            'procesador' => 'nullable|string|max:100',
            'pantalla' => 'nullable|string|max:50',
            'bateria' => 'nullable|integer|min:1|max:11000',
        ];
    }

    public function messages(): array
    {
        return [
            'id_marca.required' => 'El campo marca es requerido',
            'id_marca.integer' => 'El campo marca debe ser un número entero',
            'id_marca.exists' => 'La marca seleccionada no existe',
            'nombre_modelo.required' => 'El nombre del modelo es requerido',
            'nombre_modelo.string' => 'El nombre del modelo debe ser texto',
            'nombre_modelo.max' => 'El nombre del modelo no puede tener más de 50 caracteres',
            'nombre_modelo.unique' => 'Este modelo ya existe para la marca seleccionada',
            'ram.integer' => 'La RAM debe ser un número entero',
            'ram.min' => 'La RAM debe ser al menos 1GB',
            'ram.max' => 'La RAM no puede ser mayor a 128GB',
            'almacenamiento.integer' => 'El almacenamiento debe ser un número entero',
            'almacenamiento.min' => 'El almacenamiento debe ser al menos 1GB',
            'almacenamiento.max' => 'El almacenamiento no puede ser mayor a 1024GB (1TB)',
            'procesador.string' => 'El procesador debe ser texto',
            'procesador.max' => 'El procesador no puede tener más de 100 caracteres',
            'pantalla.string' => 'La pantalla debe ser texto',
            'pantalla.max' => 'La pantalla no puede tener más de 50 caracteres',
            'bateria.integer' => 'La batería debe ser un número entero',
            'bateria.min' => 'La batería debe ser al menos 1mAh',
            'bateria.max' => 'La batería no puede ser mayor a 11000mAh',
        ];
    }

    public function attributes(): array
    {
        return [
            'id_marca' => 'marca',
            'nombre_modelo' => 'nombre del modelo',
            'ram' => 'RAM',
            'almacenamiento' => 'almacenamiento',
            'procesador' => 'procesador',
            'pantalla' => 'pantalla',
            'bateria' => 'batería',
        ];
    }

    /**
     * Genera la regla unique condicionalmente
     */
    private function uniqueRule($modeloId)
    {
        if ($this->has('id_marca')) {
            return Rule::unique('modelo')->where(function ($query) {
                return $query->where('id_marca', $this->id_marca);
            })->ignore($modeloId, 'id_modelo');
        }

        return Rule::unique('modelo')->where(function ($query) use ($modeloId) {
            $modeloActual = \App\Models\Modelo::find($modeloId);
            if ($modeloActual) {
                return $query->where('id_marca', $modeloActual->id_marca);
            }
            return $query;
        })->ignore($modeloId, 'id_modelo');
    }

    public function prepareForValidation()
    {
        if ($this->has('id_marca') && is_string($this->id_marca)) {
            $this->merge([
                'id_marca' => (int) $this->id_marca
            ]);
        }
        
        // Convertir campos vacíos a null
        $fields = ['ram', 'almacenamiento', 'bateria', 'procesador', 'pantalla'];
        foreach ($fields as $field) {
            if ($this->has($field) && ($this->$field === '' || $this->$field === null)) {
                $this->merge([$field => null]);
            }
        }
    }
}