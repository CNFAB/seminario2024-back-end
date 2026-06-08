<?php

namespace App\Http\Requests\Garantia;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGarantiaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Cambiar a true para permitir
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id_reparacion' => [
                'required',
                'integer',
                'exists:reparaciones,id_reparacion'
            ],
            'fecha_inicio' => [
                'nullable',
                'date',
                'before_or_equal:fecha_fin'
            ],
            'fecha_fin' => [
                'nullable',
                'date',
                'after_or_equal:fecha_inicio'
            ],
            'duracion_meses' => [
                'nullable',
                'integer',
                'min:1',
                'max:60'  // máximo 5 años
            ],
            'tipo_garantia' => [
                'nullable',
                'string',
                Rule::in(['ESTANDAR', 'EXTENDIDA', 'PREMIUM', 'CORTESIA'])
            ],
            'estado' => [
                'nullable',
                'string',
                Rule::in(['ACTIVA', 'VENCIDA', 'ANULADA', 'RECLAMADA', 'CUMPLIDA'])
            ],
            'comentario' => [
                'nullable',
                'string',
                'max:500'
            ]
        ];
    }

    /**
     * Mensajes de error personalizados.
     */
    public function messages(): array
    {
        return [
            'id_reparacion.required' => 'La reparación es obligatoria.',
            'id_reparacion.exists' => 'La reparación no existe en el sistema.',
            'fecha_inicio.date' => 'La fecha de inicio debe ser una fecha válida.',
            'fecha_inicio.before_or_equal' => 'La fecha de inicio debe ser anterior o igual a la fecha de fin.',
            'fecha_fin.date' => 'La fecha de fin debe ser una fecha válida.',
            'fecha_fin.after_or_equal' => 'La fecha de fin debe ser posterior o igual a la fecha de inicio.',
            'duracion_meses.integer' => 'La duración debe ser un número entero.',
            'duracion_meses.min' => 'La duración debe ser al menos 1 mes.',
            'duracion_meses.max' => 'La duración no puede exceder 60 meses (5 años).',
            'tipo_garantia.in' => 'El tipo de garantía no es válido.',
            'estado.in' => 'El estado de la garantía no es válido.',
            'comentario.max' => 'El comentario no puede exceder los 500 caracteres.'
        ];
    }

    /**
     * Preparar datos para validación.
     */
    protected function prepareForValidation(): void
    {
        // Si no viene fecha_inicio, usar hoy
        if (!$this->has('fecha_inicio') || empty($this->fecha_inicio)) {
            $this->merge(['fecha_inicio' => now()->format('Y-m-d')]);
        }

        // Si no viene tipo_garantia, usar ESTANDAR
        if (!$this->has('tipo_garantia') || empty($this->tipo_garantia)) {
            $this->merge(['tipo_garantia' => 'ESTANDAR']);
        }

        // Si no viene estado, usar ACTIVA
        if (!$this->has('estado') || empty($this->estado)) {
            $this->merge(['estado' => 'ACTIVA']);
        }
    }

    /**
     * Validación después de pasar las reglas.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Si no viene fecha_fin y viene duracion_meses, calcular fecha_fin
            if (!$this->has('fecha_fin') && $this->has('duracion_meses') && $this->fecha_inicio) {
                $fechaInicio = \Carbon\Carbon::parse($this->fecha_inicio);
                $fechaFin = $fechaInicio->copy()->addMonths($this->duracion_meses);
                $this->merge(['fecha_fin' => $fechaFin->format('Y-m-d')]);
            }

            // Verificar que la reparación no tenga ya una garantía
            if ($this->has('id_reparacion')) {
                $existeGarantia = \App\Models\Garantia::where('id_reparacion', $this->id_reparacion)->exists();
                if ($existeGarantia) {
                    $validator->errors()->add(
                        'id_reparacion',
                        'Esta reparación ya tiene una garantía asociada.'
                    );
                }
            }
        });
    }
}