<?php

namespace App\Http\Requests\Dispositivo;

use Illuminate\Foundation\Http\FormRequest;

class StoreDispositivoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            "id_cliente" => "required|integer|exists:cliente,id_cliente",
            "id_modelo" => "required|integer|exists:modelo,id_modelo", 
            "imei" => "nullable|string|max:30|unique:dispositivo,imei",
            "codigo_interno" => "nullable|string|max:20|unique:dispositivo,codigo_interno"
        ];
    }

    public function messages(): array
    {
        return [
            "id_cliente.required" => "El cliente es requerido",
            "id_cliente.exists" => "El cliente seleccionado no existe",
            "id_modelo.required" => "El modelo es requerido",
            "id_modelo.exists" => "El modelo seleccionado no existe",
            "imei.unique" => "Este IMEI ya está registrado en otro dispositivo",
            "imei.max" => "El IMEI no puede tener más de 30 caracteres",
            "codigo_interno.unique" => "Este código interno ya está en uso",
            "codigo_interno.max" => "El código interno no puede tener más de 20 caracteres"
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

        // ✅ NUEVO: Generar código interno automáticamente si no hay IMEI
        if (empty($this->imei) && empty($this->codigo_interno)) {
            try {
                $codigoGenerado = \App\Models\Dispositivo::generarCodigoInterno($this->id_modelo);
                $this->merge([
                    'codigo_interno' => $codigoGenerado
                ]);
            } catch (\Exception $e) {
                // Si falla la generación, no hacemos nada y dejamos que falle la validación
            }
        }
    }
}