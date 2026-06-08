<?php

namespace App\Http\Requests\Ingreso_d;

use Illuminate\Foundation\Http\FormRequest;

class StoreIngresoDRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_dispositivo' => 'required|integer|exists:dispositivo,id_dispositivo',
            'id_usuario' => 'required|integer|exists:usuario,id_usuario',
            'memoria_sd' => 'required|boolean',
            'sim' => 'required|boolean',
            // ⚠️ CORRECCIÓN: fecha_ingreso es opcional (la maneja timestamps)
            'fecha_ingreso' => 'nullable|date',
            // ⚠️ CORRECCIÓN: Campos opcionales con valores por defecto
            'estado_del_ingreso' => 'nullable|string|max:500',
            'comentario_cliente' => 'nullable|string|max:500',
            'revision_tecnica' => 'required|boolean',
            'foto_frontal' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
            'foto_trasera' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
        ];
    }

    public function messages(): array
    {
        return [
            'id_dispositivo.required' => 'El ID del dispositivo es requerido',
            'id_dispositivo.exists' => 'El dispositivo seleccionado no existe',
            'id_usuario.required' => 'El ID del usuario es requerido',
            'id_usuario.exists' => 'El usuario seleccionado no existe',
            'memoria_sd.required' => 'El campo de memoria SD es requerido',
            'sim.required' => 'El campo SIM es requerido',
            'fecha_ingreso.date' => 'La fecha de ingreso debe ser una fecha válida',
            'estado_del_ingreso.max' => 'El estado del ingreso no puede tener más de 500 caracteres',
            'comentario_cliente.max' => 'El comentario no puede tener más de 500 caracteres',
            'revision_tecnica.required' => 'El campo de revisión técnica es requerido',
            'foto_frontal.image' => 'El archivo frontal debe ser una imagen',
            'foto_frontal.mimes' => 'La imagen frontal debe ser JPEG, PNG, JPG o GIF',
            'foto_frontal.max' => 'La imagen frontal no debe exceder los 5MB',
            'foto_trasera.image' => 'El archivo trasero debe ser una imagen',
            'foto_trasera.mimes' => 'La imagen trasera debe ser JPEG, PNG, JPG o GIF',
            'foto_trasera.max' => 'La imagen trasera no debe exceder los 5MB'
        ];
    }

    public function attributes(): array
    {
        return [
            'id_dispositivo' => 'dispositivo',
            'id_usuario' => 'usuario',
            'memoria_sd' => 'memoria SD',
            'sim' => 'SIM',
            'fecha_ingreso' => 'fecha de ingreso',
            'estado_del_ingreso' => 'estado del ingreso',
            'comentario_cliente' => 'comentario del cliente',
            'revision_tecnica' => 'revisión técnica',
            'foto_frontal' => 'foto frontal',
            'foto_trasera' => 'foto trasera'
        ];
    }
}