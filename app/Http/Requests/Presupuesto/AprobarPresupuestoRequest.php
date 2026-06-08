<?php

namespace App\Http\Requests\Presupuesto;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AprobarPresupuestoRequest extends FormRequest
{
    public function authorize()
    {
      return true;

    public function rules()
    {
        return [
            'accion' => ['required', Rule::in(['APROBAR', 'RECHAZAR'])],
            'comentario' => 'nullable|string|max:500',
            'detalles_aprobados' => 'required_if:accion,APROBAR|array',
            'detalles_aprobados.*' => 'integer|exists:presupuesto_detalle,id_detalle'
        ];
    }

    public function messages()
    {
        return [
            'accion.required' => 'Debe especificar si aprueba o rechaza',
            'accion.in' => 'Acción no válida',
            'detalles_aprobados.required_if' => 'Debe seleccionar los detalles a aprobar'
        ];
    }
}