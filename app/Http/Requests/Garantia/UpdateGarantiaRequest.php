<?php

namespace App\Http\Requests\Garantia;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGarantiaRequest extends FormRequest
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
            'fecha_inicio' => [
                'sometimes',
                'required',
                'date',
                'before_or_equal:fecha_fin'
            ],
            'fecha_fin' => [
                'sometimes',
                'required',
                'date',
                'after_or_equal:fecha_inicio'
            ],
            'duracion_meses' => [
                'sometimes',
                'required',
                'integer',
                'min:1',
                'max:60'
            ],
            'tipo_garantia' => [
                'sometimes',
                'required',
                'string',
                Rule::in(['ESTANDAR', 'EXTENDIDA', 'PREMIUM', 'CORTESIA'])
            ],
            'estado' => [
                'sometimes',
                'required',
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
            'fecha_inicio.required' => 'La fecha de inicio es obligatoria.',
            'fecha_inicio.date' => 'La fecha de inicio debe ser una fecha válida.',
            'fecha_inicio.before_or_equal' => 'La fecha de inicio debe ser anterior o igual a la fecha de fin.',
            'fecha_fin.required' => 'La fecha de fin es obligatoria.',
            'fecha_fin.date' => 'La fecha de fin debe ser una fecha válida.',
            'fecha_fin.after_or_equal' => 'La fecha de fin debe ser posterior o igual a la fecha de inicio.',
            'duracion_meses.required' => 'La duración es obligatoria.',
            'duracion_meses.integer' => 'La duración debe ser un número entero.',
            'duracion_meses.min' => 'La duración debe ser al menos 1 mes.',
            'duracion_meses.max' => 'La duración no puede exceder 60 meses (5 años).',
            'tipo_garantia.required' => 'El tipo de garantía es obligatorio.',
            'tipo_garantia.in' => 'El tipo de garantía no es válido. Valores permitidos: ESTANDAR, EXTENDIDA, PREMIUM, CORTESIA.',
            'estado.required' => 'El estado de la garantía es obligatorio.',
            'estado.in' => 'El estado de la garantía no es válido. Valores permitidos: ACTIVA, VENCIDA, ANULADA, RECLAMADA, CUMPLIDA.',
            'comentario.max' => 'El comentario no puede exceder los 500 caracteres.'
        ];
    }

    /**
     * Atributos personalizados.
     */
    public function attributes(): array
    {
        return [
            'fecha_inicio' => 'fecha de inicio',
            'fecha_fin' => 'fecha de fin',
            'duracion_meses' => 'duración en meses',
            'tipo_garantia' => 'tipo de garantía',
            'estado' => 'estado',
            'comentario' => 'comentario'
        ];
    }

    /**
     * Preparar datos para validación.
     */
    protected function prepareForValidation(): void
    {
        // Si se envía duracion_meses pero no fecha_fin, calcular fecha_fin
        if ($this->has('duracion_meses') && !$this->has('fecha_fin') && $this->has('fecha_inicio')) {
            $fechaInicio = \Carbon\Carbon::parse($this->fecha_inicio);
            $fechaFin = $fechaInicio->copy()->addMonths($this->duracion_meses);
            $this->merge(['fecha_fin' => $fechaFin->format('Y-m-d')]);
        }

        // Limpiar comentario si viene vacío
        if ($this->has('comentario') && empty($this->comentario)) {
            $this->merge(['comentario' => null]);
        }
    }

    /**
     * Validación después de pasar las reglas.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Verificar que al menos un campo viene para actualizar
            $campos = ['fecha_inicio', 'fecha_fin', 'duracion_meses', 'tipo_garantia', 'estado', 'comentario'];
            $tieneCampos = false;
            
            foreach ($campos as $campo) {
                if ($this->has($campo)) {
                    $tieneCampos = true;
                    break;
                }
            }
            
            if (!$tieneCampos) {
                $validator->errors()->add(
                    'campos',
                    'Debe enviar al menos un campo para actualizar: fecha_inicio, fecha_fin, duracion_meses, tipo_garantia, estado o comentario'
                );
            }
        });
    }
}