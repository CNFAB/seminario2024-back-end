<?php

namespace App\Http\Requests\Presupuesto;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePresupuestoRequest extends FormRequest
{
    public function authorize()
    {
       return true;
    }

    public function rules()
    {
        return [
            'estado' => ['sometimes', Rule::in(['PENDIENTE', 'APROBADO', 'RECHAZADO','CALCULADO'])],
            'fecha_validez' => 'sometimes|date|after:today',
            'total_estimado' => 'sometimes|numeric|min:0'
        ];
    }

    public function messages()
    {
        return [
            'estado.in' => 'El estado debe ser PENDIENTE, APROBADO o RECHAZADO o CALCULADO',
            'fecha_validez.after' => 'La fecha de validez debe ser posterior a hoy'
        ];
    }
}