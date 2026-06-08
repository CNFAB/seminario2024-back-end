<?php

namespace App\Http\Requests\Cliente;

use Illuminate\Foundation\Http\FormRequest;

class LoginClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'correo' => 'required|email',
            'contrasena' => 'required|string'
        ];
    }

    public function messages(): array
    {
        return [
            'correo.required' => 'El correo electrónico es requerido',
            'correo.email' => 'El correo electrónico debe ser válido',
            'contrasena.required' => 'La contraseña es requerida'
        ];
    }
}