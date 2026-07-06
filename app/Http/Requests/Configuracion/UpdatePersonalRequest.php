<?php

namespace App\Http\Requests\Configuracion;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePersonalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre_completo' => ['required', 'string', 'max:150'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'numero_documento' => ['required', 'string', 'max:30'],
            'fecha_ingreso' => ['nullable', 'date'],
            'puesto_id' => ['nullable', 'integer'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }
}
