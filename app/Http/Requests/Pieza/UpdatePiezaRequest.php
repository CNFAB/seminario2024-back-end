<?php

namespace App\Http\Requests\Pieza;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePiezaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
   public function rules(): array
{
    $piezaId = $this->route('id');
    
    return [
        'nombre_pieza' => [
            'sometimes',
            'required_without_all:stock,precio,id_categoria',
            'string',
            'max:255',
            Rule::unique('pieza', 'nombre_pieza')
                ->where(function ($query) {
                    return $query->where('id_categoria', $this->id_categoria);
                })
                ->ignore($piezaId, 'id_pieza')
        ],
        'stock' => [
            'sometimes',
            'required_without_all:nombre_pieza,precio,id_categoria',
            'integer',
            'min:0',
            'max:32767'
        ],
        'precio' => [
            'sometimes',
            'required_without_all:nombre_pieza,stock,id_categoria',
            'numeric',
            'min:0',
            'max:99999999.99',
            'regex:/^\d+(\.\d{1,2})?$/'
        ],
        'id_categoria' => [
            'sometimes',
            'required_without_all:nombre_pieza,stock,precio',
            'integer',
            'exists:categoria,id_categoria'
        ]
    ];
}
}
