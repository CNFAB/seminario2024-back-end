<?php

namespace App\Http\Requests\Marca;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMarcaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $marcaId = $this->route('id'); // Obtiene el ID de la ruta

        return [
            'marca' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                Rule::unique('marca')->ignore($marcaId, 'id_marca')
            ]
        ];
    }

    public function messages(): array
    {
        return [
            'marca.required' => 'El nombre de la marca es obligatorio',
            'marca.string' => 'El nombre de la marca debe ser texto',
            'marca.max' => 'El nombre de la marca no puede tener más de 20 caracteres',
            'marca.unique' => 'Este nombre de marca ya existe'
        ];
    }

    public function attributes(): array
    {
        return [
            'marca' => 'nombre de la marca'
        ];
    }
}