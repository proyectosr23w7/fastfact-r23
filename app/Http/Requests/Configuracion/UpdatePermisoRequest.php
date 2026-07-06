<?php

namespace App\Http\Requests\Configuracion;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePermisoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'codigo' => ['required', 'string', 'max:120'],
            'descripcion' => ['required', 'string', 'max:255'],
            'modulo' => ['required', 'string', 'max:80'],
        ];
    }
}
