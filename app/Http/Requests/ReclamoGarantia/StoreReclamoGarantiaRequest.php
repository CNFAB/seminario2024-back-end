<?php

namespace App\Http\Requests\ReclamoGarantia;

use Illuminate\Foundation\Http\FormRequest;

class StoreReclamoGarantiaRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Solo clientes autenticados pueden crear reclamos
        return true;
    }

    public function rules(): array
    {
        return [
            'id_garantia' => 'required|exists:garantia,id_garantia',
            'id_garantia_pieza' => 'nullable|exists:garantia_pieza,id_garantia_pieza',
            'descripcion_problema' => 'required|string|min:5|max:500',
            'fotos' => 'nullable|array|max:5',
            'fotos.*' => 'string|url', // Si son URLs
            'monto_reclamado' => 'nullable|numeric|min:0|max:999999.99'
        ];
    }

    public function messages(): array
    {
        return [
            'id_garantia.required' => 'La garantía es requerida',
            'id_garantia.exists' => 'La garantía no existe',
            'descripcion_problema.required' => 'Debes describir el problema',
            'descripcion_problema.min' => 'La descripción debe tener al menos 5 caracteres',
            'descripcion_problema.max' => 'La descripción no puede superar los 500 caracteres',
            'fotos.max' => 'Solo se permiten hasta 5 fotos',
            'monto_reclamado.numeric' => 'El monto debe ser un número válido',
            'monto_reclamado.min' => 'El monto no puede ser negativo'
        ];
    }

    protected function prepareForValidation(): void
    {
        // Si no viene monto_reclamado, usar 0
        if (!$this->has('monto_reclamado')) {
            $this->merge(['monto_reclamado' => 0]);
        }
    }
}