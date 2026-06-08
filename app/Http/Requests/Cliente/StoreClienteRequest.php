<?php

namespace App\Http\Requests\Cliente;  

use Illuminate\Foundation\Http\FormRequest;

class StoreClienteRequest extends FormRequest  
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => 'required|string|min:3|max:15',
            'apellido' => 'required|string|min:3|max:15',
            'numero_celular' => 'required|string|max:15|unique:cliente,numero_celular',
            'correo' => 'required|email|max:100|unique:cliente,correo',
            'contrasena' => 'required|string|min:6'
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es requerido',
            'nombre.min' => 'El nombre debe tener al menos 3 caracteres',
            'nombre.max' => 'El nombre no puede tener más de 15 caracteres',
            'apellido.required' => 'El apellido es requerido',
            'apellido.min' => 'El apellido debe tener al menos 3 caracteres',
            'apellido.max' => 'El apellido no puede tener más de 15 caracteres',
            'numero_celular.required' => 'El número de celular es requerido',
            'numero_celular.unique' => 'Este número de celular ya está registrado',
            'correo.required' => 'El correo electrónico es requerido',
            'correo.email' => 'El correo electrónico debe ser válido',
            'correo.unique' => 'Este correo electrónico ya está registrado',
            'contrasena.required' => 'La contraseña es requerida',
            'contrasena.min' => 'La contraseña debe tener al menos 6 caracteres'
        ];
    }

    public function attributes(): array
    {
        return [
            'nombre' => 'nombre',
            'apellido' => 'apellido',
            'numero_celular' => 'número de celular',
            'correo' => 'correo electrónico',
            'contrasena' => 'contraseña'
        ];
    }
}