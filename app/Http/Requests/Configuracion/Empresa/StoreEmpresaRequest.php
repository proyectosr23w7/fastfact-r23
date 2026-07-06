<?php

namespace App\Http\Requests\Configuracion\Empresa;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class StoreEmpresaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre_empresa' => ['required', 'string', 'max:150'],
            'razon_social' => ['required', 'string', 'max:150'],
            'nit' => ['nullable', 'string', 'max:30'],
            'propietario' => ['nullable', 'string', 'max:150'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'correo' => ['nullable', 'email', 'max:150'],
            'logo' => [
                'nullable',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (is_string($value)) {
                        if (mb_strlen($value) > 255) {
                            $fail('La ruta del logo no puede superar los 255 caracteres.');
                        }

                        return;
                    }

                    if (! $value instanceof UploadedFile || ! $value->isValid()) {
                        $fail('El logo seleccionado no es un archivo válido.');

                        return;
                    }

                    if ($value->getSize() > 2 * 1024 * 1024) {
                        $fail('El logo no puede superar los 2 MB.');
                    }

                    if (! in_array($value->getMimeType(), ['image/png', 'image/jpeg', 'image/webp'], true)) {
                        $fail('El logo debe ser una imagen PNG, JPG o WEBP.');
                    }
                },
            ],
            'estado' => ['sometimes', 'boolean'],
        ];
    }
}
