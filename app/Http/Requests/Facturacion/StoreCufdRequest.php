<?php

namespace App\Http\Requests\Facturacion;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCufdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sucursal_id' => ['nullable', 'integer', Rule::exists('sucursales', 'id')],
            'punto_venta_id' => ['nullable', 'integer', Rule::exists('puntos_venta', 'id')],
            'forzar_nuevo' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            /** @var User|null $user */
            $user = $this->user();
            $sucursalId = $this->integer('sucursal_id');
            $puntoVentaId = $this->integer('punto_venta_id');

            if (! $user) {
                $validator->errors()->add('sucursal_id', 'No se pudo identificar el usuario autenticado.');
                return;
            }

            if (! $sucursalId) {
                $validator->errors()->add('sucursal_id', 'La sucursal es obligatoria para generar un nuevo CUFD.');
            }

            if (! $puntoVentaId) {
                $validator->errors()->add('punto_venta_id', 'El punto de venta es obligatorio para generar un nuevo CUFD.');
            }

            if (! $sucursalId || ! $puntoVentaId) {
                return;
            }

            $exists = \App\Models\Configuracion\PuntoVenta::query()
                ->whereKey($puntoVentaId)
                ->where('sucursal_id', $sucursalId)
                ->exists();

            if (! $exists) {
                $validator->errors()->add('punto_venta_id', 'El punto de venta no pertenece a la sucursal seleccionada.');
            }
        });
    }
}
