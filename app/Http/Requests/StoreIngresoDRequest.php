<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreIngresoDRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }


    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id_dispositivo' => 'required',
            'id_usuario' => 'required',
            'memoria_sd' => 'required',
            'sim' => 'required',
            'fecha_ingreso' => 'required',
            'estado_del_ingreso' => 'required',
            'revision_tecnica' => 'required'
        ];
    }
    public function messages()
    {
        return [
            'id_dispositivo.required' => 'el ID del dispositivo es requerido',
            'id_usuario.required' => 'el ID del usuario es requerido',
            'memoria_sd.required' => 'El campo de la memoria es requerida ',
            'sim.required' => 'El campo de la SIM es requerida',
            'fecha_ingreso' => 'La fecha de ingreso es requerida',
            'estado_del_ingreso' => 'El estado de ingreso es requerido',
            'revision_tecnica' => 'El campo de revision tecnica es requerida'
        ];
    }
}
