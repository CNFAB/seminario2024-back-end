<?php
namespace App\Http\Requests\Diagnostico;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDiagnosticoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'costo' => 'sometimes|numeric|min:0|max:99999999.99',
            'observacion' => 'nullable|string|max:500',
            'gravedad' => 'sometimes|in:URGENTE,MODERADO,LEVE',
            'causa_detectada' => 'nullable|string|max:1000',
            'solucion' => 'nullable|string|max:1000',
            'estado' => 'sometimes|in:ESPERANDO_DIAGNOSTICO,EN_REVISION,ESPERANDO_APROBACION,NO_REPARADO,EN_REPARACION,EN_ESPERA_DE_PIEZAS,LISTO_PARA_RETIRAR,APROBADO,RECHAZADO',
            'costo_reparacion' => 'nullable|numeric|min:0',
            'fecha_expiracion' => 'nullable|date',
            'id_precio_r' => 'nullable|integer', // Sin exists para evitar lentitud
            'id_pieza' => 'nullable|integer|exists:pieza,id_pieza', // ✅ NUEVO CAMPO
        ];
    }

    public function messages(): array
    {
        return [
            'costo.numeric' => 'El costo debe ser un valor numérico.',
            'costo.min' => 'El costo no puede ser negativo.',
            'gravedad.in' => 'La gravedad debe ser URGENTE, MODERADO o LEVE.',
            'estado.in' => 'El estado seleccionado no es válido.',
            'id_pieza.exists' => 'La pieza seleccionada no existe.', // ✅ MENSAJE NUEVO
            'causa_detectada.max' => 'La causa detectada no puede exceder 1000 caracteres.',
            'solucion.max' => 'La solución no puede exceder 1000 caracteres.',
            'observacion.max' => 'La observación no puede exceder 500 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('observacion')) {
            $this->merge([
                'observacion' => mb_strtoupper($this->observacion, 'UTF-8')
            ]);
        }
    }
}