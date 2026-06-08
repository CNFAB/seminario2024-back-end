<?php

namespace App\Http\Requests\Dispositivo;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDispositivoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ← CAMBIAR a true para permitir acceso
    }

    public function rules(): array
    {
        $dispositivoId = $this->route('dispositivo'); // Obtener ID desde la ruta

        return [
            'id_cliente' => 'sometimes|required|integer|exists:cliente,id_cliente',
            'id_modelo' => 'sometimes|required|integer|exists:modelo,id_modelo',
            'imei' => [
                'sometimes',
                'nullable',
                'string',
                'max:30',
                Rule::unique('dispositivo')->ignore($dispositivoId, 'id_dispositivo')
            ],
            'codigo_interno' => [
                'sometimes',
                'nullable', 
                'string',
                'max:20',
                Rule::unique('dispositivo')->ignore($dispositivoId, 'id_dispositivo')
            ]
        ];
    }

    public function messages(): array
    {
        return [
            'id_cliente.required' => 'El cliente es requerido',
            'id_cliente.integer' => 'El ID del cliente debe ser un número',
            'id_cliente.exists' => 'El cliente seleccionado no existe',
            'id_modelo.required' => 'El modelo es requerido',
            'id_modelo.integer' => 'El ID del modelo debe ser un número',
            'id_modelo.exists' => 'El modelo seleccionado no existe',
            'imei.unique' => 'Este IMEI ya está registrado en otro dispositivo',
            'imei.max' => 'El IMEI no puede tener más de 30 caracteres',
            'codigo_interno.unique' => 'Este código interno ya está en uso',
            'codigo_interno.max' => 'El código interno no puede tener más de 20 caracteres'
        ];
    }

    public function attributes(): array
    {
        return [
            'id_cliente' => 'cliente',
            'id_modelo' => 'modelo',
            'imei' => 'IMEI',
            'codigo_interno' => 'código interno'
        ];
    }

    public function prepareForValidation()
    {
        // Convertir a integer si vienen como string
        if ($this->has('id_cliente') && is_string($this->id_cliente)) {
            $this->merge([
                'id_cliente' => (int) $this->id_cliente
            ]);
        }

        if ($this->has('id_modelo') && is_string($this->id_modelo)) {
            $this->merge([
                'id_modelo' => (int) $this->id_modelo
            ]);
        }

        // Si el IMEI está vacío, convertirlo a null
        if ($this->has('imei') && empty(trim($this->imei))) {
            $this->merge([
                'imei' => null
            ]);
        }

        // Si el código interno está vacío, convertirlo a null
        if ($this->has('codigo_interno') && empty(trim($this->codigo_interno))) {
            $this->merge([
                'codigo_interno' => null
            ]);
        }
    }
}
