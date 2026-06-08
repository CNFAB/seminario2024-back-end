<?php

namespace App\Http\Requests\ReclamoGarantia;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReclamoGarantiaByClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = auth()->user();
        return $user && $user->esCliente();
    }

    public function rules(): array
    {
        return [
            'descripcion_problema' => 'sometimes|required|string|min:5|max:500',
            'fotos' => 'nullable|array|max:5',
            'fotos.*' => 'string|url'
        ];
    }

    public function messages(): array
    {
        return [
            'descripcion_problema.required' => 'La descripción del problema es requerida',
            'descripcion_problema.min' => 'La descripción debe tener al menos 5 caracteres',
            'descripcion_problema.max' => 'La descripción no puede superar los 500 caracteres',
            'fotos.max' => 'Solo se permiten hasta 5 fotos'
        ];
    }
}