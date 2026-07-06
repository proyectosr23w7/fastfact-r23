<?php

namespace App\Http\Requests\Facturacion;

use Illuminate\Foundation\Http\FormRequest;

class CloseEventoSignificativoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fecha_fin' => ['required', 'date'],
            'observacion_interna' => ['nullable', 'string'],
        ];
    }
}
