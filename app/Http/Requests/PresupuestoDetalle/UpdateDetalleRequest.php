<?php

namespace App\Http\Requests\PresupuestoDetalle;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDetalleRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'descripcion' => 'nullable|string|max:255',
            'costo' => 'sometimes|numeric|min:0',
            'aprobado' => 'sometimes|boolean'
        ];
    }

    public function messages()
    {
        return [
            'costo.min' => 'El costo no puede ser negativo'
        ];
    }
}