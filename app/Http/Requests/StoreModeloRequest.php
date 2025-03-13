<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreModeloRequest extends FormRequest
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
        return [
           'id_marca'=>'required|max:10',
           'nombre_modelo'=>'required|max:40'
        ];
    }
    public function messages()
    {
        return[
            'id_marca.required'=>'el campo id_marca es requerido',
            'id_marca.max'=>'el tamaño maximo de id_marca es de 10',
            'nombre_modelo.required'=>'El campo del nombre de modelo es requerido',
            'nombre_modelo.max'=>'la longitud maxima  del campo de nombre del modelo es de 40 '
        ];

    }
}
