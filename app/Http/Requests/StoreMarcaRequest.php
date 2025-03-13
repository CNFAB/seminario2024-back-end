<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMarcaRequest extends FormRequest
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
            'marca'=>'required|max:20|unique:marca'
        ];
    }
    public function messages()
    {
        return[
            'marca.required'=>'la marca es obligatoria',
            'marca.unique'=>'El nombre de la MARCA ya esta en la base de datos',
            'marca.unique'=>'el nombre de la MarCA tiene que ser menor a 20 caracteres'
        ];
    }

}
