<?php

namespace App\Http\Requests\Facturacion;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMetodoPagoOperativoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'habilitado_venta' => ['sometimes', 'boolean'],
            'es_predeterminado' => ['sometimes', 'boolean'],
            'orden_operativo' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
