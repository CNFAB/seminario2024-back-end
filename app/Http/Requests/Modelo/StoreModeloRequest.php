<?php

namespace App\Http\Requests\Modelo;

use Illuminate\Foundation\Http\FormRequest;

class StoreModeloRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_marca' => 'required|integer|exists:marca,id_marca',
            'nombre_modelo' => 'required|string|max:50',
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
}