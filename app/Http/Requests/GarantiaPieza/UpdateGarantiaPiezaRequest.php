<?php

namespace App\Http\Requests\GarantiaPieza;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGarantiaPiezaRequest extends FormRequest
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
            'garantia_dias_asignados' => [
                'sometimes',
                'required',
                'integer',
                'min:1',
                'max:1095'
            ],
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
            'estado' => [
                'sometimes',
                'required',
                'string',
                Rule::in(['ACTIVA', 'VENCIDA', 'RECLAMADA', 'CUMPLIDA'])
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
            'garantia_dias_asignados.required' => 'Los días de garantía son obligatorios.',
            'garantia_dias_asignados.integer' => 'Los días de garantía deben ser un número entero.',
            'garantia_dias_asignados.min' => 'Los días de garantía deben ser al menos 1 día.',
            'garantia_dias_asignados.max' => 'Los días de garantía no pueden exceder 1095 días (3 años).',
            'fecha_inicio.required' => 'La fecha de inicio es obligatoria.',
            'fecha_inicio.date' => 'La fecha de inicio debe ser una fecha válida.',
            'fecha_inicio.before_or_equal' => 'La fecha de inicio debe ser anterior o igual a la fecha de fin.',
            'fecha_fin.required' => 'La fecha de fin es obligatoria.',
            'fecha_fin.date' => 'La fecha de fin debe ser una fecha válida.',
            'fecha_fin.after_or_equal' => 'La fecha de fin debe ser posterior o igual a la fecha de inicio.',
            'estado.required' => 'El estado es obligatorio.',
            'estado.in' => 'El estado no es válido. Valores permitidos: ACTIVA, VENCIDA, RECLAMADA, CUMPLIDA.',
            'comentario.max' => 'El comentario no puede exceder los 500 caracteres.'
        ];
    }

    /**
     * Atributos personalizados.
     */
    public function attributes(): array
    {
        return [
            'garantia_dias_asignados' => 'días de garantía',
            'fecha_inicio' => 'fecha de inicio',
            'fecha_fin' => 'fecha de fin',
            'estado' => 'estado',
            'comentario' => 'comentario'
        ];
    }

    /**
     * Preparar datos para validación.
     */
    protected function prepareForValidation(): void
    {
        // Si se actualiza garantia_dias_asignados y no viene fecha_fin, recalcular
        if ($this->has('garantia_dias_asignados') && !$this->has('fecha_fin')) {
            $garantiaPieza = \App\Models\GarantiaPieza::find($this->route('id'));
            if ($garantiaPieza) {
                $fechaInicio = $this->has('fecha_inicio') 
                    ? \Carbon\Carbon::parse($this->fecha_inicio) 
                    : \Carbon\Carbon::parse($garantiaPieza->fecha_inicio);
                $fechaFin = $fechaInicio->copy()->addDays($this->garantia_dias_asignados);
                $this->merge(['fecha_fin' => $fechaFin->format('Y-m-d')]);
            }
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
            $campos = ['garantia_dias_asignados', 'fecha_inicio', 'fecha_fin', 'estado', 'comentario'];
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
                    'Debe enviar al menos un campo para actualizar: garantia_dias_asignados, fecha_inicio, fecha_fin, estado o comentario'
                );
            }

            // Verificar que no se pueda cambiar estado si ya está vencida
            if ($this->has('estado') && $this->estado === 'ACTIVA') {
                $garantiaPieza = \App\Models\GarantiaPieza::find($this->route('id'));
                if ($garantiaPieza && $garantiaPieza->fecha_fin < now()) {
                    $validator->errors()->add(
                        'estado',
                        'No se puede reactivar una garantía vencida.'
                    );
                }
            }
        });
    }
}