<?php

namespace App\Http\Requests\Usuario;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUsuarioRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $usuarioId = $this->route('id'); // Obtener ID de la ruta

        return [
            'nombre' => 'sometimes|required|string|min:2|max:100',
            'apellido' => 'sometimes|required|string|min:2|max:100',
            'correo' => [
                'sometimes',
                'required',
                'email',
                'max:100',
                Rule::unique('usuario', 'correo')->ignore($usuarioId, 'id_usuario')
            ],
            'numero_celular' => [
                'nullable',
                'string',
                'max:20',
                // Validación personalizada para teléfono argentino
                function ($attribute, $value, $fail) {
                    if ($value) { // Solo validar si no es null
                        // Limpiar el número (quitar espacios, guiones, paréntesis)
                        $clean = preg_replace('/[\s\-\(\)]/', '', $value);
                        
                        // Validar formato argentino
                        if (!preg_match('/^\d{10}$/', $clean) && 
                            !preg_match('/^9\d{10}$/', $clean) &&
                            !preg_match('/^(11\d{2}|[2-9]\d{2,3})15\d{6,7}$/', $clean)) {
                            $fail('El número de teléfono no tiene un formato válido para Argentina.');
                        }
                    }
                },
            ],
            'contrasena' => 'sometimes|string|min:6|max:255|nullable',
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
            'numero_celular.max' => 'El teléfono no puede tener más de 20 caracteres',
            'contrasena.min' => 'La contraseña debe tener al menos 6 caracteres',
            'contrasena.max' => 'La contraseña no puede tener más de 255 caracteres',
            'es_tecnico.boolean' => 'El campo técnico debe ser verdadero o falso',
            'es_recepcionista.boolean' => 'El campo recepcionista debe ser verdadero o falso',
            'es_administrador.boolean' => 'El campo administrador debe ser verdadero o falso',
            'activo.boolean' => 'El campo activo debe ser verdadero o falso',
        ];
    }
}