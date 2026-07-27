<?php

namespace App\Http\Requests\Usuario;

use App\Models\Configuracion\PuntoVenta;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('usuario')?->id ?? $this->route('user')?->id;

        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($userId)],
            'password' => ['nullable', 'string', 'min:8'],
            'sucursal_id' => ['nullable', 'integer', Rule::exists('sucursales', 'id')],
            'punto_venta_id' => ['nullable', 'integer', Rule::exists('puntos_venta', 'id')],
            'estado' => ['sometimes', 'boolean'],
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['integer', Rule::exists('roles', 'id')],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'sucursal_id' => $this->input('sucursal_id') ?: null,
            'punto_venta_id' => $this->input('punto_venta_id') ?: null,
        ]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $sucursalId = $this->integer('sucursal_id');
            $puntoVentaId = $this->integer('punto_venta_id');

            if (! $puntoVentaId) {
                return;
            }

            $belongsToSucursal = PuntoVenta::query()
                ->whereKey($puntoVentaId)
                ->when($sucursalId > 0, fn ($query) => $query->where('sucursal_id', $sucursalId))
                ->exists();

            if (! $belongsToSucursal) {
                $validator->errors()->add('punto_venta_id', 'El punto de venta no pertenece a la sucursal seleccionada.');
            }
        });
    }
}
