<?php

namespace App\Http\Requests\Venta;

use App\Enums\VentaEstadoEnum;
use App\Models\VentaCabecera;

class UpdateVentaRequest extends StoreVentaRequest
{
    public function after(): array
    {
        return array_merge(parent::after(), [
            function ($validator) {
                $venta = $this->route('venta');

                if ($venta instanceof VentaCabecera && $venta->estado !== VentaEstadoEnum::BORRADOR) {
                    $validator->errors()->add('estado', 'Solo se pueden editar ventas en borrador.');
                }
            },
        ]);
    }
}
