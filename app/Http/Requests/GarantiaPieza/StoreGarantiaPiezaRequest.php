<?php

namespace App\Http\Requests\GarantiaPieza;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGarantiaPiezaRequest extends FormRequest
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
            'id_garantia' => [
                'required',
                'integer',
                'exists:garantia,id_garantia'
            ],
            'id_reparacion_multiple' => [
                'required',
                'integer',
                'exists:reparaciones_multiples,id_multiple'
            ],
            'id_pieza' => [
                'required',
                'integer',
                'exists:piezas,id_pieza'
            ],
            'id_categoria' => [
                'nullable',
                'integer',
                'exists:categoria,id_categoria'
            ],
            'garantia_dias_asignados' => [
                'required',
                'integer',
                'min:1',
                'max:1095'
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
            'estado' => [
                'nullable',
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
            'id_garantia.required' => 'La garantía es obligatoria.',
            'id_garantia.exists' => 'La garantía no existe en el sistema.',
            'id_reparacion_multiple.required' => 'La reparación múltiple es obligatoria.',
            'id_reparacion_multiple.exists' => 'La reparación múltiple no existe en el sistema.',
            'id_pieza.required' => 'La pieza es obligatoria.',
            'id_pieza.exists' => 'La pieza no existe en el sistema.',
            'id_categoria.exists' => 'La categoría no existe en el sistema.',
            'garantia_dias_asignados.required' => 'Los días de garantía son obligatorios.',
            'garantia_dias_asignados.integer' => 'Los días de garantía deben ser un número entero.',
            'garantia_dias_asignados.min' => 'Los días de garantía deben ser al menos 1 día.',
            'garantia_dias_asignados.max' => 'Los días de garantía no pueden exceder 1095 días (3 años).',
            'fecha_inicio.date' => 'La fecha de inicio debe ser una fecha válida.',
            'fecha_inicio.before_or_equal' => 'La fecha de inicio debe ser anterior o igual a la fecha de fin.',
            'fecha_fin.date' => 'La fecha de fin debe ser una fecha válida.',
            'fecha_fin.after_or_equal' => 'La fecha de fin debe ser posterior o igual a la fecha de inicio.',
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
            'id_garantia' => 'garantía',
            'id_reparacion_multiple' => 'reparación múltiple',
            'id_pieza' => 'pieza',
            'id_categoria' => 'categoría',
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
        // Si no viene fecha_inicio, usar la fecha de la garantía
        if (!$this->has('fecha_inicio') && $this->has('id_garantia')) {
            $garantia = \App\Models\Garantia::find($this->id_garantia);
            if ($garantia) {
                $this->merge(['fecha_inicio' => $garantia->fecha_inicio]);
            } else {
                $this->merge(['fecha_inicio' => now()->format('Y-m-d')]);
            }
        }

        // Si no viene fecha_fin, calcular según garantia_dias_asignados
        if (!$this->has('fecha_fin') && $this->has('garantia_dias_asignados') && $this->has('fecha_inicio')) {
            $fechaInicio = \Carbon\Carbon::parse($this->fecha_inicio);
            $fechaFin = $fechaInicio->copy()->addDays($this->garantia_dias_asignados);
            $this->merge(['fecha_fin' => $fechaFin->format('Y-m-d')]);
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
            // Verificar que la garantía existe y está activa
            if ($this->has('id_garantia')) {
                $garantia = \App\Models\Garantia::find($this->id_garantia);
                if ($garantia && $garantia->estado !== 'ACTIVA') {
                    $validator->errors()->add(
                        'id_garantia',
                        'La garantía no está activa. No se pueden agregar piezas.'
                    );
                }
            }

            // Verificar que la reparación múltiple pertenece a la garantía
            if ($this->has('id_garantia') && $this->has('id_reparacion_multiple')) {
                $reparacionMultiple = \App\Models\ReparacionesMultiple::find($this->id_reparacion_multiple);
                $garantia = \App\Models\Garantia::find($this->id_garantia);
                
                if ($reparacionMultiple && $garantia) {
                    if ($reparacionMultiple->id_reparacion != $garantia->id_reparacion) {
                        $validator->errors()->add(
                            'id_reparacion_multiple',
                            'La reparación múltiple no pertenece a la misma reparación que la garantía.'
                        );
                    }
                }
            }

            // Verificar que no exista ya una garantía para esta pieza en esta garantía
            if ($this->has('id_garantia') && $this->has('id_reparacion_multiple')) {
                $existe = \App\Models\GarantiaPieza::where('id_garantia', $this->id_garantia)
                    ->where('id_reparacion_multiple', $this->id_reparacion_multiple)
                    ->exists();
                
                if ($existe) {
                    $validator->errors()->add(
                        'id_reparacion_multiple',
                        'Ya existe una garantía para esta pieza en esta garantía.'
                    );
                }
            }
        });
    }
}