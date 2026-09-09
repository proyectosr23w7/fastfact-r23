<?php

namespace App\Http\Requests\Venta;

use App\Support\DocumentoIdentidad;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (filled($this->input('tipo_documento_identidad'))) {
            return;
        }

        $tipoDocumento = DocumentoIdentidad::inferirTipo($this->input('nit_ci'));

        if ($tipoDocumento !== null) {
            $this->merge(['tipo_documento_identidad' => $tipoDocumento]);
        }
    }

    public function rules(): array
    {
        $clienteId = $this->route('cliente')?->id;

        return [
            'razon_social' => ['required', 'string', 'max:200'],
            'nit_ci' => [
                'required',
                'string',
                'max:50',
                Rule::unique('clientes')->ignore($clienteId)->where(fn ($query) => $query
                    ->where('tipo_documento_identidad', $this->input('tipo_documento_identidad'))
                    ->where('complemento', $this->input('complemento'))
                ),
            ],
            'tipo_documento_identidad' => ['required', 'string', 'max:10'],
            'complemento' => ['nullable', 'string', 'max:20'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'correo' => ['nullable', 'email', 'max:150'],
            'estado' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $tipo = (string) $this->input('tipo_documento_identidad', '');
            $documento = (string) $this->input('nit_ci', '');
            $complemento = (string) $this->input('complemento', '');

            if (in_array($tipo, ['1', '5'], true) && ! preg_match('/^[0-9]+$/', $documento)) {
                $validator->errors()->add('nit_ci', 'Para CI y NIT el numero de documento debe ser numerico.');
            }

            if (preg_match('/^0+$/', trim($documento)) === 1) {
                $validator->errors()->add('nit_ci', 'El numero de documento debe ser distinto de 0 para emitir factura.');
            }

            if ($tipo !== '1' && $complemento !== '') {
                $validator->errors()->add('complemento', 'El complemento solo aplica cuando el tipo de documento es CI.');
            }
        });
    }
}
