<?php

namespace App\Http\Requests\ArticuloPrecio;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreArticuloPrecioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'articulo_id' => ['required', 'integer', Rule::exists('articulos', 'id')],
            'cantidad_minima' => ['required', 'numeric', 'min:1'],
            'precio' => ['required', 'numeric', 'min:0'],
            'tipo_precio' => ['nullable', 'string', 'max:50'],
            'estado' => ['sometimes', 'boolean'],
        ];
    }
}
