<?php

namespace App\Http\Requests\Presupuesto;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePresupuestoRequest extends FormRequest
{
    public function authorize()
    {
        // Verificar si el usuario tiene permiso para crear presupuestos
        return true;
    }

    public function rules()
    {
        return [
            'id_ingreso' => 'required|integer|exists:ingreso_d,id_ingreso',
            'fecha_validez' => 'required|date|after:today',
            'total_estimado' => 'nullable|numeric|min:0',
            'detalles' => 'sometimes|array',
            'detalles.*.id_pieza' => 'required_with:detalles|integer|exists:pieza,id_pieza',
            'detalles.*.descripcion' => 'nullable|string|max:255',
            'detalles.*.costo' => 'required_with:detalles|numeric|min:0'
        ];
    }

    public function messages()
    {
        return [
            'id_ingreso.required' => 'El ingreso es obligatorio',
            'id_ingreso.exists' => 'El ingreso seleccionado no existe',
            'fecha_validez.required' => 'La fecha de validez es obligatoria',
            'fecha_validez.after' => 'La fecha de validez debe ser posterior a hoy',
            'detalles.*.id_pieza.exists' => 'Una de las piezas seleccionadas no existe',
            'detalles.*.costo.min' => 'El costo no puede ser negativo'
        ];
    }

    public function prepareForValidation()
    {
        // Si no viene total_estimado, lo dejamos en 0
        if (!$this->has('total_estimado')) {
            $this->merge(['total_estimado' => 0]);
        }
    }
}