<?php

namespace App\Http\Requests\UnidadMedida;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUnidadMedidaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $unidadMedidaId = $this->route('unidad_medida')?->id;

        return [
            'nombre' => ['required', 'string', 'max:150', Rule::unique('unidades_medida', 'nombre')->ignore($unidadMedidaId)],
            'abreviatura' => ['required', 'string', 'max:20', Rule::unique('unidades_medida', 'abreviatura')->ignore($unidadMedidaId)],
            'estado' => ['sometimes', 'boolean'],
        ];
    }
}
