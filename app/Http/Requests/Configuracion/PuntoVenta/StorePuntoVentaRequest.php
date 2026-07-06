<?php

namespace App\Http\Requests\Configuracion\PuntoVenta;

use App\Enums\TipoImpresionEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StorePuntoVentaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sucursal_id' => ['required', 'integer'],
            'codigo' => ['required', 'integer', 'min:0'],
            'nombre' => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'tipo_impresion' => ['required', new Enum(TipoImpresionEnum::class)],
            'estado' => ['sometimes', 'boolean'],
        ];
    }
}
