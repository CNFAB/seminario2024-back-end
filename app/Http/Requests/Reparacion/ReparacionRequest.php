<?php
namespace App\Http\Requests\Reparacion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Diagnostico;
use App\Models\Usuario;
use App\Models\Ingreso_d;

class ReparacionRequest extends FormRequest
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
        return [
            'id_diagnostico' => [
                'nullable',
                'integer',
                'exists:diagnostico,id_diagnostico',
                // Validar que no esté ya asignado a otra reparación
                Rule::unique('reparacion', 'id_diagnostico'),
                // Validar que el diagnóstico esté aprobado
                function ($attribute, $value, $fail) {
                    if (!empty($value)) {
                        $diagnostico = Diagnostico::find($value);
                        if (!$diagnostico) {
                            $fail('El diagnóstico no existe.');
                        } elseif ($diagnostico->estado !== 'APROBADO') {
                            $fail('El diagnóstico debe estar en estado APROBADO.');
                        }
                    }
                }
            ],
            
            'id_ingreso' => [
                'nullable',
                'integer',
                'exists:ingreso_d,id_ingreso',
                // Validar que no esté ya asignado a otra reparación
                Rule::unique('reparacion', 'id_ingreso'),
                // Validar que el ingreso tenga dispositivo
                function ($attribute, $value, $fail) {
                    if (!empty($value)) {
                        $ingreso = Ingreso_d::find($value);
                        if (!$ingreso) {
                            $fail('El ingreso no existe.');
                        } elseif (!$ingreso->id_dispositivo) {
                            $fail('El ingreso no tiene un dispositivo asociado.');
                        }
                    }
                }
            ],
            
            'id_usuario' => [
                'required',
                'integer',
                'exists:usuario,id_usuario',
                // Validar que el usuario sea técnico
                function ($attribute, $value, $fail) {
                    $usuario = Usuario::find($value);
                    if (!$usuario) {
                        $fail('El usuario no existe.');
                    } elseif ($usuario->es_tecnico != 1 && $usuario->es_administrador != 1) {
                        $fail('El usuario asignado debe ser un técnico o administrador.');
                    }
                }
            ],
            
            'comentario' => [
                'nullable',
                'string',
                'max:500'
            ],
            
            // REGLA CRÍTICA: Al menos uno de los dos (diagnóstico O ingreso) debe estar presente
            'al_menos_uno' => [
                'required',
                function ($attribute, $value, $fail) {
                    $tieneDiagnostico = !empty($this->id_diagnostico);
                    $tieneIngreso = !empty($this->id_ingreso);
                    
                    if (!$tieneDiagnostico && !$tieneIngreso) {
                        $fail('Debe proporcionar al menos un diagnóstico o un ingreso para crear la reparación.');
                    }
                    
                    // Opcional: Si quieres que tenga ambos (XOR sería: si tiene uno, NO puede tener el otro)
                    // if ($tieneDiagnostico && $tieneIngreso) {
                    //     $fail('Solo puede proporcionar un diagnóstico O un ingreso, no ambos.');
                    // }
                    
                    // O si quieres que tenga exactamente uno (XOR lógico):
                    // if (!($tieneDiagnostico xor $tieneIngreso)) {
                    //     $fail('Debe proporcionar exactamente un diagnóstico O un ingreso, pero no ambos.');
                    // }
                }
            ]
        ];
    }

    public function messages(): array
    {
        return [
            'id_diagnostico.integer' => 'El ID del diagnóstico debe ser un número entero.',
            'id_diagnostico.exists' => 'El diagnóstico seleccionado no existe.',
            'id_diagnostico.unique' => 'Este diagnóstico ya tiene una reparación asignada.',
            
            'id_ingreso.integer' => 'El ID del ingreso debe ser un número entero.',
            'id_ingreso.exists' => 'El ingreso seleccionado no existe.',
            'id_ingreso.unique' => 'Este ingreso ya tiene una reparación asignada.',
            
            'id_usuario.required' => 'El técnico es obligatorio.',
            'id_usuario.integer' => 'El ID del técnico debe ser un número entero.',
            'id_usuario.exists' => 'El usuario seleccionado no existe.',
            
            'comentario.string' => 'El comentario debe ser texto.',
            'comentario.max' => 'El comentario no puede exceder los 500 caracteres.',
            
            'al_menos_uno.required' => 'Debe proporcionar al menos un diagnóstico o un ingreso.',
        ];
    }

    public function attributes(): array
    {
        return [
            'id_diagnostico' => 'diagnóstico',
            'id_ingreso' => 'ingreso',
            'id_usuario' => 'técnico',
            'comentario' => 'comentario',
            'al_menos_uno' => 'referencia de reparación',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'al_menos_uno' => true, // Siempre true, la validación real está en la regla
        ]);
        
        // Limpiar comentario
        if ($this->has('comentario')) {
            $this->merge([
                'comentario' => trim(strip_tags($this->comentario)) ?: null,
            ]);
        }
        
        // Normalizar id_diagnostico (permitir null/empty)
        if ($this->has('id_diagnostico')) {
            $diagnostico = $this->id_diagnostico;
            if (empty($diagnostico) || $diagnostico == 0 || $diagnostico === 'null') {
                $this->merge(['id_diagnostico' => null]);
            } elseif (is_numeric($diagnostico)) {
                $this->merge(['id_diagnostico' => (int) $diagnostico]);
            }
        }
        
        // Normalizar id_ingreso
        if ($this->has('id_ingreso') && is_numeric($this->id_ingreso)) {
            $this->merge(['id_ingreso' => (int) $this->id_ingreso]);
        }
        
        // Normalizar id_usuario
        if ($this->has('id_usuario') && is_numeric($this->id_usuario)) {
            $this->merge(['id_usuario' => (int) $this->id_usuario]);
        }
    }

    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);
        
        // Remover el campo de validación personalizado
        unset($validated['al_menos_uno']);
        
        return $validated;
    }
}