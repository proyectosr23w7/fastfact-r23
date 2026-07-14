<?php

namespace App\Http\Requests\Facturacion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventoSignificativoRequest extends FormRequest
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
            'cufd_evento_id' => ['nullable', 'integer', Rule::exists('cufd', 'id')],
            'cafc_id' => ['nullable', 'integer', Rule::exists('cafc', 'id')],
            'codigo_evento' => ['required', 'string', Rule::in(['1', '2', '3', '4', '5', '6', '7'])],
            'descripcion' => ['required', 'string', 'max:255'],
            'fecha_inicio' => ['required', 'date'],
            'observacion_interna' => ['nullable', 'string'],
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

            if (in_array((string) $this->input('codigo_evento'), ['5', '6', '7'], true) && ! $this->filled('cafc_id')) {
                $validator->errors()->add('cafc_id', 'Debes seleccionar un CAFC para eventos significativos manuales 5 al 7.');
            }

            if (in_array((string) $this->input('codigo_evento'), ['5', '6', '7'], true) && ! $this->filled('cufd_evento_id')) {
                $validator->errors()->add('cufd_evento_id', 'Debes seleccionar el CUFD del evento para eventos significativos manuales 5 al 7.');
            }
        });
    }
}
