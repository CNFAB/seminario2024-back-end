<?php

namespace App\Http\Requests\Reparacion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Diagnostico;
use App\Models\Ingreso_d;

class UpdateReparacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // return auth()->check() && (
        //     auth()->user()->es_tecnico == 1 || 
        //     auth()->user()->es_administrador == 1
        // );
        return true;
    }

    public function rules(): array
    {
        $reparacionId = $this->route('reparacion') ?? $this->route('id');
        
        // Primero, obtener la reparación actual para saber qué tiene
        $reparacionActual = \App\Models\Reparacion::find($reparacionId);
        
        return [
            'id_diagnostico' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:diagnostico,id_diagnostico',
                Rule::unique('reparacion', 'id_diagnostico')
                    ->ignore($reparacionId, 'id_reparacion'),
                function ($attribute, $value, $fail) {
                    if (!empty($value)) {
                        $diagnostico = Diagnostico::find($value);
                        if ($diagnostico && $diagnostico->estado !== 'APROBADO') {
                            $fail('El diagnóstico debe estar aprobado.');
                        }
                    }
                }
            ],
            
            'id_ingreso' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:ingreso_d,id_ingreso',
                Rule::unique('reparacion', 'id_ingreso')
                    ->ignore($reparacionId, 'id_reparacion'),
                function ($attribute, $value, $fail) {
                    if (!empty($value)) {
                        $ingreso = Ingreso_d::find($value);
                        if ($ingreso && !$ingreso->id_dispositivo) {
                            $fail('El ingreso no tiene dispositivo.');
                        }
                    }
                }
            ],
            
            'id_usuario' => [
                'sometimes',
                'integer',
                'exists:usuario,id_usuario',
            ],
             'created_at' => [
                'sometimes',
                'nullable',
                'date',
                'date_format:Y-m-d H:i:s'
            ],
            
            'updated_at' => [
                'sometimes',
                'nullable',
                'date',
                'date_format:Y-m-d H:i:s'
            ],
            
            'comentario' => [
                'sometimes',
                'nullable',
                'string',
                'max:500'
            ],
            
            // VALIDACIÓN CRÍTICA: No permitir dejar ambos campos vacíos
            'al_menos_uno' => [
                'sometimes',
                'required',
                function ($attribute, $value, $fail) use ($reparacionActual) {
                    // Si NO se está enviando ninguno de los dos campos, está bien (no se actualizan)
                    $estaEnviandoDiagnostico = $this->has('id_diagnostico');
                    $estaEnviandoIngreso = $this->has('id_ingreso');
                    
                    if (!$estaEnviandoDiagnostico && !$estaEnviandoIngreso) {
                        // No está tratando de actualizar estos campos, está bien
                        return;
                    }
                    
                    // Determinar qué valor tendrá después de la actualización
                    $nuevoDiagnostico = $estaEnviandoDiagnostico 
                        ? ($this->id_diagnostico === '' ? null : $this->id_diagnostico)
                        : $reparacionActual->id_diagnostico;
                    
                    $nuevoIngreso = $estaEnviandoIngreso
                        ? ($this->id_ingreso === '' ? null : $this->id_ingreso)
                        : $reparacionActual->id_ingreso;
                    
                    // Validar que al menos uno tenga valor
                    if (empty($nuevoDiagnostico) && empty($nuevoIngreso)) {
                        $fail('La reparación debe tener al menos un diagnóstico O un ingreso asociado.');
                    }
                    
                    // Opcional: Si quieres validación XOR (solo uno de los dos)
                    // if (!empty($nuevoDiagnostico) && !empty($nuevoIngreso)) {
                    //     $fail('La reparación solo puede tener un diagnóstico O un ingreso, no ambos.');
                    // }
                }
            ]
        ];
    }

    public function messages(): array
    {
        return [
            'id_diagnostico.unique' => 'Este diagnóstico ya está en otra reparación.',
            'id_ingreso.unique' => 'Este ingreso ya tiene una reparación.',
            'al_menos_uno.required' => 'Debe mantener al menos un diagnóstico o ingreso.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Solo agregar al_menos_uno si se están actualizando esos campos
        if ($this->has('id_diagnostico') || $this->has('id_ingreso')) {
            $this->merge(['al_menos_uno' => true]);
        }
    }
      
}