<?php

namespace App\Http\Requests\PresupuestoDetalle;

use Illuminate\Foundation\Http\FormRequest;

class StoreDetalleRequest extends FormRequest
{
    public function authorize()
    {
       return true;
    }

    public function rules()
    {
        return [
            'id_presupuesto' => 'required|integer|exists:presupuesto,id_presupuesto',
            'id_pieza' => 'required|integer|exists:pieza,id_pieza',
            'descripcion' => 'nullable|string|max:255',
            'costo' => 'required|numeric|min:0',
            'aprobado' => 'sometimes|boolean'
        ];
    }

    public function messages()
    {
        return [
            'id_presupuesto.required' => 'El presupuesto es obligatorio',
            'id_presupuesto.exists' => 'El presupuesto no existe',
            'id_pieza.required' => 'La pieza es obligatoria',
            'id_pieza.exists' => 'La pieza seleccionada no existe',
            'costo.required' => 'El costo es obligatorio',
            'costo.min' => 'El costo no puede ser negativo'
        ];
    }
}