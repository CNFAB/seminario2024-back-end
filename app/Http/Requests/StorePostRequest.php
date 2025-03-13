<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePostRequest extends FormRequest
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
        //aqui guardamos las reglas de las validacciones 
        return [
            'nombre'=>'required|min:3|max:20',
            'apellido'=>'required|min:3|max:20',
            'numero_celular'=>'required',
            'correo'=>'required'

        ];
    }
}
