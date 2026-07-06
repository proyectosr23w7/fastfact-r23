<?php

namespace App\Actions\Facturacion;

use App\Enums\TipoFacturacionEnum;
use App\Enums\VentaEstadoEnum;
use App\Models\Configuracion\Configuracion;
use App\Models\VentaCabecera;

class ValidarVentaFacturableAction
{
    public function __invoke(VentaCabecera $venta, Configuracion $configuracion): void
    {
        if (! $configuracion->facturacion_habilitada) {
            abort(422, 'La facturacion no esta habilitada en la configuracion del sistema.');
        }

        if ((int) $configuracion->tipo_facturacion === TipoFacturacionEnum::NO_EMITE->value) {
            abort(422, 'La configuracion actual indica que esta instalacion no emite facturas.');
        }

        if ($venta->estado !== VentaEstadoEnum::CONFIRMADA) {
            abort(422, 'Solo una venta confirmada puede convertirse en factura.');
        }

        if (! $venta->requiere_factura) {
            abort(422, 'La venta no fue marcada para emitir factura.');
        }

        if (! $venta->cliente) {
            abort(422, 'La venta no tiene cliente asociado.');
        }

        if (
            ! filled($venta->cliente->razon_social ?: $venta->cliente->nombre)
            || ! filled($venta->cliente->nit_ci)
            || ! filled($venta->cliente->tipo_documento_identidad)
        ) {
            abort(422, 'El cliente debe contar con razon social, tipo de documento y numero de documento para facturar.');
        }

        if (preg_match('/^0+$/', trim((string) $venta->cliente->nit_ci)) === 1) {
            abort(422, 'El cliente facturable debe tener un numero de documento valido y distinto de 0.');
        }

        $subtotal = round((float) $venta->detalle->sum('subtotal'), 2);
        $descuentoItems = round((float) $venta->detalle->sum('descuento'), 2);
        $descuentoGlobal = round((float) ($venta->descuento_global ?? $venta->descuento ?? 0), 2);

        foreach ($venta->detalle as $detalle) {
            if ((float) $detalle->descuento > (float) $detalle->subtotal) {
                abort(422, 'Existe al menos un descuento lineal mayor al subtotal de su linea. Corrige la venta antes de facturar.');
            }
        }

        if ($descuentoGlobal > round(max($subtotal - $descuentoItems, 0), 2)) {
            abort(422, 'El descuento global no puede ser mayor al subtotal neto luego de descuentos por item.');
        }

        if (round(max($subtotal - $descuentoItems - $descuentoGlobal, 0), 2) <= 0) {
            abort(422, 'La venta facturable debe conservar un total mayor a 0 despues de aplicar descuentos lineales y globales.');
        }
    }
}
