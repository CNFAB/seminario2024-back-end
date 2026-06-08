<?php
namespace App\Http\Requests\Diagnostico;

use Illuminate\Foundation\Http\FormRequest;

class StoreDiagnosticoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'costo' => 'required|numeric|min:0|max:99999999.99',
            'observacion' => 'nullable|string|max:500',
            'id_ingreso' => 'required|integer|exists:ingreso_d,id_ingreso',
            'id_usuario' => 'required|integer|exists:usuario,id_usuario',
            'id_precio_r' => 'nullable|integer|exists:precio_reparacion,id_precio_reparacion',
            'id_pieza' => 'nullable|integer|exists:pieza,id_pieza',
            'costo_reparacion' => 'nullable|numeric|min:0',
            'fecha_expiracion' => 'nullable|date',
            'gravedad' => 'nullable|in:URGENTE,MODERADO,LEVE',
            'causa_detectada' => 'nullable|string|max:1000',
            'solucion' => 'nullable|string|max:1000',
            'estado' => 'nullable|in:ESPERANDO_DIAGNOSTICO,ESPERANDO_APROBACION,NO_REPARADO,EN_REPARACION,EN_ESPERA_DE_PIEZAS,LISTO_PARA_RETIRAR'
        ];
    }

    public function messages(): array
    {
        return [
            'costo.required' => 'El costo es obligatorio.',
            'costo.numeric' => 'El costo debe ser un número.',
            'id_ingreso.required' => 'El ingreso es obligatorio.',
            'id_ingreso.exists' => 'El ingreso no existe.',
            'id_usuario.required' => 'El técnico es obligatorio.',
            'id_usuario.exists' => 'El técnico no existe.',
            'id_pieza.exists' => 'La pieza seleccionada no existe.', // ✅ MENSAJE NUEVO
            'gravedad.in' => 'La gravedad debe ser URGENTE, MODERADO o LEVE.',
            'estado.in' => 'El estado seleccionado no es válido.',
        ];
    }
}