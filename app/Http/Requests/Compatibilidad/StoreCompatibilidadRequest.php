<?php

namespace App\Http\Requests\compatibilidad;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompatibilidadRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a hacer esta petición
     */
    public function authorize(): bool
    {
        return true; // Cambiar según tu lógica de autenticación
    }

    /**
     * Reglas de validación para la petición de creación
     */
    public function rules(): array
    {
        return [
            'id_pieza' => [
                'required',
                'integer',
                'exists:pieza,id_pieza',
                // Regla unique para evitar duplicados en creación
                Rule::unique('compatibilidad')
                    ->where(function ($query) {
                        return $query->where('id_modelo', $this->id_modelo);
                    })
            ],
            'id_modelo' => [
                'required',
                'integer',
                'exists:modelo,id_modelo',
                // Regla unique para evitar duplicados en creación
                Rule::unique('compatibilidad')
                    ->where(function ($query) {
                        return $query->where('id_pieza', $this->id_pieza);
                    })
            ],
            'descripcion' => [
                'nullable',
                'string',
                'max:200',
                'min:3'
            ]
        ];
    }

    /**
     * Validación adicional después de las reglas básicas
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validar que la pieza tenga stock disponible
            if ($this->has('id_pieza') && $this->id_pieza) {
                $pieza = \App\Models\Pieza::find($this->id_pieza);
                if ($pieza && $pieza->stock <= 0) {
                    $validator->errors()->add(
                        'id_pieza',
                        'La pieza seleccionada no tiene stock disponible'
                    );
                }
            }

            // Validar que el modelo exista y esté activo (si tienes campo activo)
            if ($this->has('id_modelo') && $this->id_modelo) {
                $modelo = \App\Models\Modelo::with('marca')->find($this->id_modelo);
                if (!$modelo) {
                    $validator->errors()->add(
                        'id_modelo',
                        'El modelo seleccionado no existe'
                    );
                }
            }

            // Validación personalizada: verificar si ya existe la combinación
            // (aunque la regla unique ya lo hace, esta es una validación más explícita)
            if ($this->has('id_pieza') && $this->has('id_modelo')) {
                $existe = \App\Models\Compatibilidad::where('id_pieza', $this->id_pieza)
                    ->where('id_modelo', $this->id_modelo)
                    ->exists();
                
                if ($existe) {
                    $validator->errors()->add(
                        'general',
                        'Ya existe una compatibilidad entre esta pieza y este modelo'
                    );
                }
            }
        });
    }

    /**
     * Mensajes personalizados de error
     */
    public function messages(): array
    {
        return [
            'id_pieza.required' => 'La pieza es obligatoria',
            'id_pieza.integer' => 'El ID de la pieza debe ser un número entero',
            'id_pieza.exists' => 'La pieza seleccionada no existe en el sistema',
            'id_pieza.unique' => 'Ya existe una compatibilidad con esta pieza para el modelo seleccionado',
            
            'id_modelo.required' => 'El modelo es obligatorio',
            'id_modelo.integer' => 'El ID del modelo debe ser un número entero',
            'id_modelo.exists' => 'El modelo seleccionado no existe en el sistema',
            'id_modelo.unique' => 'Ya existe una compatibilidad con este modelo para la pieza seleccionada',
            
            'descripcion.max' => 'La descripción no puede tener más de 200 caracteres',
            'descripcion.min' => 'La descripción debe tener al menos 3 caracteres'
        ];
    }

    /**
     * Preparar los datos para la validación
     */
    protected function prepareForValidation()
    {
        // Limpiar y preparar los datos antes de la validación
        $data = [];
        
        // Convertir IDs a enteros
        if ($this->has('id_pieza')) {
            $data['id_pieza'] = (int) $this->id_pieza;
        }
        
        if ($this->has('id_modelo')) {
            $data['id_modelo'] = (int) $this->id_modelo;
        }
        
        // Limpiar descripción si viene
        if ($this->has('descripcion') && $this->descripcion) {
            $data['descripcion'] = trim($this->descripcion);
        } else {
            $data['descripcion'] = null;
        }
        
        $this->merge($data);
    }

    /**
     * Obtener los datos validados para crear la compatibilidad
     */
    public function getCompatibilityData(): array
    {
        return [
            'id_pieza' => $this->id_pieza,
            'id_modelo' => $this->id_modelo,
            'descripcion' => $this->descripcion
        ];
    }

    /**
     * Obtener datos adicionales para logging o auditoría
     */
    public function getAuditData(): array
    {
        $pieza = \App\Models\Pieza::find($this->id_pieza);
        $modelo = \App\Models\Modelo::with('marca')->find($this->id_modelo);
        
        return [
            'pieza_nombre' => $pieza ? $pieza->nombre_pieza : null,
            'modelo_nombre' => $modelo ? $modelo->nombre_modelo : null,
            'marca_nombre' => $modelo && $modelo->marca ? $modelo->marca->marca : null,
            'descripcion' => $this->descripcion,
            'fecha_creacion' => now()
        ];
    }

    /**
     * Verificar si la combinación es válida
     */
    public function isValidCombination(): bool
    {
        // Aquí puedes agregar lógica de negocio adicional
        // Por ejemplo, verificar que la categoría de la pieza sea compatible con el modelo
        
        $pieza = \App\Models\Pieza::with('categoria')->find($this->id_pieza);
        $modelo = \App\Models\Modelo::find($this->id_modelo);
        
        if (!$pieza || !$modelo) {
            return false;
        }
        
        // Ejemplo: Verificar si la categoría de la pieza es válida para el modelo
        // $categoriasPermitidas = ['camara_trasera', 'bateria', 'pantalla'];
        // return in_array($pieza->categoria->categoria, $categoriasPermitidas);
        
        return true;
    }
}