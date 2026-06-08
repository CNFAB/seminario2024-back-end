<?php

namespace App\Http\Requests\Usuario;

use Illuminate\Foundation\Http\FormRequest;

class StoreUsuarioRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'nombre' => 'required|string|min:2|max:100',
            'apellido' => 'required|string|min:2|max:100',
            'correo' => 'required|email|max:100|unique:usuario,correo',
            'numero_celular' => [
                'required',
                'string',
                'max:20',
                'unique:usuario,numero_celular', // ✅ Agregar validación de unicidad
                function ($attribute, $value, $fail) {
                    // Limpiar el número
                    $clean = preg_replace('/[\s\-\(\)]/', '', $value);
                    
                    // Validar formato argentino
                    if (!preg_match('/^\d{10}$/', $clean) && 
                        !preg_match('/^9\d{10}$/', $clean) &&
                        !preg_match('/^(11\d{2}|[2-9]\d{2,3})15\d{6,7}$/', $clean)) {
                        $fail('El número de teléfono no tiene un formato válido para Argentina.');
                    }
                },
            ],
            'contrasena' => 'required|string|min:6|max:255',
            'es_tecnico' => 'sometimes|boolean',
            'es_recepcionista' => 'sometimes|boolean',
            'es_administrador' => 'sometimes|boolean',
            'activo' => 'sometimes|boolean'
        ];
    }

    public function messages()
    {
        return [
            'nombre.required' => 'El nombre es obligatorio',
            'nombre.min' => 'El nombre debe tener al menos 2 caracteres',
            'nombre.max' => 'El nombre no puede tener más de 100 caracteres',
            'apellido.required' => 'El apellido es obligatorio',
            'apellido.min' => 'El apellido debe tener al menos 2 caracteres',
            'apellido.max' => 'El apellido no puede tener más de 100 caracteres',
            'correo.required' => 'El correo es obligatorio',
            'correo.email' => 'El correo debe tener un formato válido',
            'correo.unique' => 'Este correo ya está registrado',
            'correo.max' => 'El correo no puede tener más de 100 caracteres',
            'numero_celular.required' => 'El número de teléfono es obligatorio',
            'numero_celular.unique' => 'Este número de teléfono ya está registrado', // ✅ Mensaje de unicidad
            'numero_celular.max' => 'El teléfono no puede tener más de 20 caracteres',
            'contrasena.required' => 'La contraseña es obligatoria',
            'contrasena.min' => 'La contraseña debe tener al menos 6 caracteres',
            'contrasena.max' => 'La contraseña no puede tener más de 255 caracteres',
        ];
    }
}