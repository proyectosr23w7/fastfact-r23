<?php

namespace App\Http\Requests\Facturacion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncCatalogosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sucursal_id' => ['required', 'integer', Rule::exists('sucursales', 'id')],
            'punto_venta_id' => ['required', 'integer', Rule::exists('puntos_venta', 'id')],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $exists = \App\Models\Configuracion\PuntoVenta::query()
                ->whereKey($this->integer('punto_venta_id'))
                ->where('sucursal_id', $this->integer('sucursal_id'))
                ->exists();

            if (! $exists) {
                $validator->errors()->add('punto_venta_id', 'El punto de venta no pertenece a la sucursal seleccionada.');
            }
        });
    }
}
