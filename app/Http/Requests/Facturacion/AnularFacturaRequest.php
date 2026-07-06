<?php

namespace App\Http\Requests\Facturacion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnularFacturaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'codigo_motivo_anulacion' => ['required', 'string', Rule::exists('sin_motivos_anulacion', 'codigo_clasificador')->where('estado', true)],
            'descripcion_motivo' => ['nullable', 'string', 'max:255'],
        ];
    }
}
