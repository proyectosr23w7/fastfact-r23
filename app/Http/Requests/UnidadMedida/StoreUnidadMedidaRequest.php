<?php

namespace App\Http\Requests\UnidadMedida;

use Illuminate\Foundation\Http\FormRequest;

class StoreUnidadMedidaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:150', 'unique:unidades_medida,nombre'],
            'abreviatura' => ['required', 'string', 'max:20', 'unique:unidades_medida,abreviatura'],
            'estado' => ['sometimes', 'boolean'],
        ];
    }
}
