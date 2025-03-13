<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDispositivoRequest extends FormRequest
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
          "id_cliente"=>"required",
          "id_modelo"=>"required",
          "imei"=>""
          
        ];
    }
    public function messages()
    {
       return [
         "id_cliente.required"=>" el numero del cliente es requerido",
         "id_modelo.required"=>"el numero del modelo es requerido"
       ];
    }
}
