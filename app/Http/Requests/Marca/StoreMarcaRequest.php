<?php

namespace App\Http\Requests\Marca;

use Illuminate\Foundation\Http\FormRequest;

class StoreMarcaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'marca' => 'required|string|max:20|unique:marca'
        ];
    }

    public function messages(): array
    {
        return [
            'marca.required' => 'El nombre de la marca es obligatorio',
            'marca.string' => 'El nombre de la marca debe ser texto',
            'marca.max' => 'El nombre de la marca no puede tener más de 20 caracteres',
            'marca.unique' => 'El nombre de la marca ya existe en la base de datos'
        ];
    }

    public function attributes(): array
    {
        return [
            'marca' => 'nombre de la marca'
        ];
    }
}