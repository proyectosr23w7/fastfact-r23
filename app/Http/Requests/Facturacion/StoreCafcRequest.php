<?php

namespace App\Http\Requests\Facturacion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Configuracion\Configuracion;

class StoreCafcRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $cafcId = $this->route('cafc')?->id;

        return [
            'codigo' => [
                'required',
                'string',
                'max:120',
                Rule::unique('cafc', 'codigo')
                    ->ignore($cafcId)
                    ->where(fn ($query) => $query->where('ambiente_facturacion', $this->input('ambiente_facturacion'))),
            ],
            'pin' => ['nullable', 'string', 'max:120'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'sucursal_id' => ['required', 'integer', Rule::exists('sucursales', 'id')],
            'punto_venta_id' => ['required', 'integer', Rule::exists('puntos_venta', 'id')],
            'ambiente_facturacion' => ['required', Rule::in(['piloto', 'produccion'])],
            'fecha_inicio_vigencia' => ['nullable', 'date'],
            'fecha_fin_vigencia' => ['nullable', 'date'],
            'numero_inicial' => ['nullable', 'integer', 'min:1'],
            'numero_final' => ['nullable', 'integer', 'min:1'],
            'observacion' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $configuracion = Configuracion::current();

        $this->merge([
            'ambiente_facturacion' => $this->input('ambiente_facturacion')
                ?: (string) ($configuracion?->ambiente_facturacion ?: 'piloto'),
        ]);
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

            $inicio = $this->date('fecha_inicio_vigencia');
            $fin = $this->date('fecha_fin_vigencia');

            if ($inicio && $fin && $fin->lt($inicio)) {
                $validator->errors()->add('fecha_fin_vigencia', 'La fecha fin de vigencia no puede ser menor a la fecha inicio.');
            }

            $numeroInicial = $this->integer('numero_inicial');
            $numeroFinal = $this->integer('numero_final');
            $tieneInicial = $this->filled('numero_inicial');
            $tieneFinal = $this->filled('numero_final');

            if ($tieneInicial xor $tieneFinal) {
                $validator->errors()->add('numero_final', 'Debes registrar ambos extremos del rango autorizado del CAFC.');
            }

            if ($tieneInicial && $tieneFinal && $numeroFinal < $numeroInicial) {
                $validator->errors()->add('numero_final', 'El numero final no puede ser menor al numero inicial del CAFC.');
            }
        });
    }
}
