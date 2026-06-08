<?php

namespace App\Http\Requests\ReparacionMultiple;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\ReparacionMultiple;
use App\Models\Pieza;

class StoreRequestReparacionM extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_reparacion' => [
                'required',
                'integer',
                'exists:reparacion,id_reparacion',
            ],
            
            'id_pieza' => [
                'required',
                'integer',
                'exists:pieza,id_pieza',
                function ($attribute, $value, $fail) {
                    $pieza = \App\Models\Pieza::find($value);
                    if ($pieza && $pieza->stock < 1) {
                        $fail('No hay stock disponible para esta pieza.');
                    }
                }
            ],
            
            'estado' => [
                'nullable',
                'string',
                Rule::in(array_keys(ReparacionMultiple::ESTADOS_TECNICO)),
            ],
            
            'comentario_tecnico' => [
                'nullable',
                'string',
                'max:200'
            ],
            
            'precio_pieza_momento' => [
                'nullable',
                'numeric',
                'min:0',
                'max:9999999.99'
            ],
            
            'mano_obra_momento' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100'
            ],
            
            'precio_total' => [
                'nullable',
                'numeric',
                'min:0',
                'max:9999999.99'
            ],
            
            'fecha_ini_reparacion' => [
                'nullable',
                'date',
                'before_or_equal:now'
            ],
            
            'fecha_fin_reparacion' => [
                'nullable',
                'date',
                'after_or_equal:fecha_ini_reparacion'
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'id_reparacion.required' => 'La reparación es obligatoria.',
            'id_pieza.required' => 'La pieza es obligatoria.',
            'id_pieza.exists' => 'La pieza seleccionada no existe.',
            'comentario_tecnico.max' => 'El comentario no puede exceder los 200 caracteres.',
            'precio_total.numeric' => 'El precio total debe ser un número válido.',
            'precio_total.min' => 'El precio total no puede ser negativo.',
            'precio_pieza_momento.numeric' => 'El precio de la pieza debe ser un número válido.',
            'mano_obra_momento.numeric' => 'La mano de obra debe ser un número válido.',
            'mano_obra_momento.max' => 'La mano de obra no puede exceder el 100%.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Si viene precio_total vacío, establecerlo como null para que se calcule automáticamente
        if ($this->has('precio_total') && empty($this->precio_total)) {
            $this->merge(['precio_total' => null]);
        }
        
        // Establecer estado por defecto si no se envía
        if (!$this->has('estado') || empty($this->estado)) {
            $this->merge(['estado' => 'PENDIENTE']);
        }
        
        // Limpiar comentario
        if ($this->has('comentario_tecnico')) {
            $this->merge([
                'comentario_tecnico' => trim(strip_tags($this->comentario_tecnico)) ?: null,
            ]);
        }
        
        // Obtener datos de la pieza para precio_pieza_momento y mano_obra_momento
        if ($this->has('id_pieza') && !$this->has('precio_pieza_momento')) {
            $pieza = Pieza::with('categoria')->find($this->id_pieza);
            if ($pieza) {
                $this->merge([
                    'precio_pieza_momento' => $pieza->precio,
                    'mano_obra_momento' => $pieza->categoria->mano_obra ?? 0
                ]);
            }
        }
    }
}