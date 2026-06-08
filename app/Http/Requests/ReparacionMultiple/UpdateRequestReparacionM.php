<?php

namespace App\Http\Requests\ReparacionMultiple;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\ReparacionMultiple;
use App\Models\Pieza;

class UpdateRequestReparacionM extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $reparacionMultiple = $this->route('reparacion_multiple');
        $estadosPermitidos = array_keys(ReparacionMultiple::ESTADOS_TECNICO);

        return [
            'estado' => [
                'sometimes',
                'required',
                'string',
                'in:' . implode(',', $estadosPermitidos),
                function ($attribute, $value, $fail) use ($reparacionMultiple) {
                    if (!$reparacionMultiple) return;
                    
                    if ($reparacionMultiple->estado === 'TERMINADO' && $value !== 'TERMINADO') {
                        $fail('No se puede cambiar el estado de una reparación terminada.');
                    }
                    
                    if ($reparacionMultiple->estado === 'CANCELADO' && $value !== 'CANCELADO') {
                        $fail('No se puede cambiar el estado de una reparación cancelada.');
                    }
                    
                    if ($value === 'TERMINADO' && $reparacionMultiple->estado !== 'EN_REPARACION') {
                        $fail('Solo se puede terminar una reparación que esté en progreso.');
                    }
                    
                    if ($value === 'EN_REPARACION' && $reparacionMultiple->estado !== 'PENDIENTE') {
                        $fail('Solo se puede iniciar una reparación que esté pendiente.');
                    }
                   
                }
            ],
            
            'comentario_tecnico' => 'sometimes|nullable|string|max:200',
            
            'id_pieza' => [
                'sometimes',
                'integer',
                'exists:pieza,id_pieza',
                function ($attribute, $value, $fail) use ($reparacionMultiple) {
                    if (!$reparacionMultiple) return;
                    
                    if (!in_array($reparacionMultiple->estado, ['PENDIENTE', 'ESPERANDO_PIEZA'])) {
                        $fail('Solo se puede cambiar la pieza si la reparación está pendiente o esperando pieza.');
                    }
                    
                    $pieza = Pieza::find($value);
                    if ($pieza && $pieza->stock < 1) {
                        $fail('La nueva pieza no tiene stock disponible.');
                    }
                    
                    $existe = ReparacionMultiple::where('id_reparacion', $reparacionMultiple->id_reparacion)
                        ->where('id_pieza', $value)
                        ->where('id_multiple', '!=', $reparacionMultiple->id_multiple)
                        ->exists();
                        
                    if ($existe) {
                        $fail('Esta pieza ya está asignada a otra reparación múltiple en la misma reparación.');
                    }
                }
            ],
            
            'precio_pieza_momento' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
                'max:9999999.99'
            ],
            
            'mano_obra_momento' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
                'max:100'
            ],
            
            'precio_total' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
                'max:9999999.99',
                function ($attribute, $value, $fail) use ($reparacionMultiple) {
                    if (!$reparacionMultiple) return;
                    
                    if (!in_array($reparacionMultiple->estado, ['PENDIENTE', 'EN_REPARACION', 'ESPERANDO_PIEZA'])) {
                        $fail('Solo se puede modificar el precio en reparaciones pendientes, en reparación o esperando pieza.');
                    }
                }
            ],
            
            'fecha_ini_reparacion' => [
                'sometimes',
                'nullable',
                'date',
                'before_or_equal:now'
            ],
            
            'fecha_fin_reparacion' => [
                'sometimes',
                'nullable',
                'date',
                function ($attribute, $value, $fail) {
                    if (!empty($value) && $this->fecha_ini_reparacion) {
                        $fechaInicio = \Carbon\Carbon::parse($this->fecha_ini_reparacion);
                        $fechaFin = \Carbon\Carbon::parse($value);
                        
                        if ($fechaFin->lessThan($fechaInicio)) {
                            $fail('La fecha de fin debe ser posterior o igual a la fecha de inicio.');
                        }
                    }
                }
            ],
            
            'campos_validos' => [
                'required',
                function ($attribute, $value, $fail) {
                    $camposPermitidos = [
                        'id_reparacion',
                        'estado', 
                        'comentario_tecnico', 
                        'id_pieza', 
                        'precio_pieza_momento',
                        'mano_obra_momento',
                        'precio_total',
                        'fecha_ini_reparacion',
                        'fecha_fin_reparacion'
                    ];
                    
                    $camposPresentes = array_filter($camposPermitidos, fn($campo) => $this->has($campo));
                    
                    if (empty($camposPresentes)) {
                        $fail('Debe proporcionar al menos un campo para actualizar.');
                    }
                }
            ]
        ];
    }

    protected function passedValidation(): void
    {
        $reparacionMultiple = $this->route('reparacion_multiple');
        
        if (!$reparacionMultiple) {
            return;
        }
        
        // Manejar cambio de pieza
        if ($this->has('id_pieza') && $this->id_pieza != $reparacionMultiple->id_pieza) {
            // Devolver stock de la pieza anterior
            $piezaAnterior = Pieza::find($reparacionMultiple->id_pieza);
            if ($piezaAnterior) {
                $piezaAnterior->increment('stock', 1);
            }
            
            // Reducir stock de la nueva pieza
            $nuevaPieza = Pieza::find($this->id_pieza);
            if ($nuevaPieza) {
                $nuevaPieza->decrement('stock', 1);
            }
            
            // Actualizar precio_pieza_momento y mano_obra_momento con los valores actuales de la nueva pieza
            if ($nuevaPieza) {
                $this->merge([
                    'precio_pieza_momento' => $nuevaPieza->precio,
                    'mano_obra_momento' => $nuevaPieza->categoria->mano_obra ?? 0
                ]);
            }
            
            // Forzar recalculo de precio_total si no se proporciona
            if (!$this->has('precio_total') || $this->precio_total === null) {
                $this->merge(['precio_total' => null]);
            }
        }
        
        // Si se cambia el estado a TERMINADO y no hay precio_total, forzar recalculo
        if ($this->has('estado') && $this->estado === 'TERMINADO' && 
            (!$this->has('precio_total') || $this->precio_total === null)) {
            $this->merge(['precio_total' => null]);
        }
        
        if (config('app.debug')) {
            \Log::debug('Actualización de reparación múltiple validada', [
                'reparacion_multiple_id' => $reparacionMultiple->id_multiple,
                'usuario_id' => auth()->id(),
                'campos_actualizados' => array_keys($this->validated()),
                'estado_anterior' => $reparacionMultiple->estado,
                'estado_nuevo' => $this->estado ?? null,
            ]);
        }
    }

    public function messages(): array
    {
        return [
            'estado.required' => 'El estado es requerido.',
            'estado.in' => 'El estado seleccionado no es válido.',
            'comentario_tecnico.string' => 'El comentario debe ser texto.',
            'comentario_tecnico.max' => 'El comentario no puede exceder los 200 caracteres.',
            'id_pieza.exists' => 'La pieza no existe.',
            'precio_pieza_momento.numeric' => 'El precio de la pieza debe ser un número.',
            'mano_obra_momento.numeric' => 'La mano de obra debe ser un número.',
            'precio_total.numeric' => 'El precio total debe ser un número.',
            'precio_total.min' => 'El precio total no puede ser negativo.',
            'precio_total.max' => 'El precio total es demasiado alto.',
            'fecha_ini_reparacion.date' => 'La fecha de inicio debe ser una fecha válida.',
            'fecha_ini_reparacion.before_or_equal' => 'La fecha de inicio no puede ser futura.',
            'fecha_fin_reparacion.date' => 'La fecha de fin debe ser una fecha válida.',
            'campos_validos.required' => 'Debe proporcionar al menos un campo para actualizar.'
        ];
    }

    public function attributes(): array
    {
        return [
            'estado' => 'estado de reparación',
            'comentario_tecnico' => 'comentario del técnico',
            'id_pieza' => 'pieza asignada',
            'precio_pieza_momento' => 'precio de la pieza al momento',
            'mano_obra_momento' => 'porcentaje de mano de obra',
            'precio_total' => 'precio total',
            'fecha_ini_reparacion' => 'fecha de inicio',
            'fecha_fin_reparacion' => 'fecha de fin',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['campos_validos' => true]);
        
        if ($this->has('comentario_tecnico')) {
            $this->merge([
                'comentario_tecnico' => trim(strip_tags($this->comentario_tecnico)) ?: null,
            ]);
        }
        
        if ($this->has('precio_total')) {
            $precio = $this->precio_total;
            if ($precio === '' || $precio === 'null' || $precio === 'NULL') {
                $this->merge(['precio_total' => null]);
            } elseif (is_numeric($precio)) {
                $this->merge(['precio_total' => (float) $precio]);
            }
        }
        
        if ($this->has('precio_pieza_momento')) {
            $precio = $this->precio_pieza_momento;
            if ($precio === '' || $precio === 'null' || $precio === 'NULL') {
                $this->merge(['precio_pieza_momento' => null]);
            } elseif (is_numeric($precio)) {
                $this->merge(['precio_pieza_momento' => (float) $precio]);
            }
        }
        
        if ($this->has('mano_obra_momento')) {
            $mano = $this->mano_obra_momento;
            if ($mano === '' || $mano === 'null' || $mano === 'NULL') {
                $this->merge(['mano_obra_momento' => null]);
            } elseif (is_numeric($mano)) {
                $this->merge(['mano_obra_momento' => (float) $mano]);
            }
        }
        
        if ($this->has('fecha_ini_reparacion') && !empty($this->fecha_ini_reparacion)) {
            $this->merge([
                'fecha_ini_reparacion' => \Carbon\Carbon::parse($this->fecha_ini_reparacion)->toDateTimeString()
            ]);
        }
        
        if ($this->has('fecha_fin_reparacion') && !empty($this->fecha_fin_reparacion)) {
            $this->merge([
                'fecha_fin_reparacion' => \Carbon\Carbon::parse($this->fecha_fin_reparacion)->toDateTimeString()
            ]);
        }
    }

    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);
        unset($validated['campos_validos']);
        
        return array_filter($validated, function ($value) {
            return !is_null($value) && $value !== '';
        });
    }
}