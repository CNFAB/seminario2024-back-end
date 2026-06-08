<?php

namespace App\Http\Requests\Ingreso_d;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIngresoDRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $ingresoId = $this->route('ingreso_d');

        return [
            'id_dispositivo' => 'sometimes|required|integer|exists:dispositivo,id_dispositivo',
            'id_usuario' => 'sometimes|required|integer|exists:usuario,id_usuario',
            'memoria_sd' => 'sometimes|required|boolean',
            'sim' => 'sometimes|required|boolean',
            'fecha_ingreso' => 'sometimes|required|date',
            'estado_del_ingreso' => 'sometimes|required|string|max:30',
            'comentario_cliente' => 'sometimes|required|string|max:500',
            'revision_tecnica' => 'sometimes|required|boolean',
            'foto_frontal' => 'sometimes|nullable|string|max:255',
            'foto_trasera' => 'sometimes|nullable|string|max:255'
        ];
    }

    public function messages(): array
    {
        return [
            'id_dispositivo.exists' => 'El dispositivo seleccionado no existe',
            'id_usuario.exists' => 'El usuario seleccionado no existe',
            'memoria_sd.boolean' => 'El campo memoria SD debe ser verdadero o falso',
            'sim.boolean' => 'El campo SIM debe ser verdadero o falso',
            'fecha_ingreso.date' => 'La fecha de ingreso debe ser una fecha válida',
            'estado_del_ingreso.max' => 'El estado del ingreso no puede tener más de 30 caracteres',
            'comentario_cliente.max' => 'El comentario no puede tener más de 500 caracteres',
            'revision_tecnica.boolean' => 'El campo revisión técnica debe ser verdadero o falso',
            'foto_frontal.max' => 'La ruta de la foto frontal es demasiado larga',
            'foto_trasera.max' => 'La ruta de la foto trasera es demasiado larga'
        ];
    }

    public function prepareForValidation()
    {
        // Convertir a boolean si vienen como string
        foreach (['memoria_sd', 'sim', 'revision_tecnica'] as $field) {
            if ($this->has($field)) {
                $this->merge([
                    $field => filter_var($this->$field, FILTER_VALIDATE_BOOLEAN)
                ]);
            }
        }

        // Convertir a integer si vienen como string
        foreach (['id_dispositivo', 'id_usuario'] as $field) {
            if ($this->has($field) && is_string($this->$field)) {
                $this->merge([
                    $field => (int) $this->$field
                ]);
            }
        }
    }
}