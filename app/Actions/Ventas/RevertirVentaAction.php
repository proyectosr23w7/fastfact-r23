<?php

namespace App\Actions\Ventas;

use App\Enums\ReferenciaKardexEnum;
use App\Enums\TipoMovimientoKardexEnum;
use App\Enums\VentaEstadoEnum;
use App\Models\Articulo;
use App\Models\VentaCabecera;
use App\Services\Inventario\KardexService;

class RevertirVentaAction
{
    public function __construct(
        private readonly DescontarStockArticuloAction $descontarStockArticulo,
        private readonly RestituirLotesVentaAction $restituirLotesVenta,
        private readonly KardexService $kardexService,
    ) {
    }

    public function __invoke(VentaCabecera $venta, ?string $observacion = null): void
    {
        if ($venta->estado === VentaEstadoEnum::ANULADA) {
            abort(422, 'La venta ya fue anulada previamente.');
        }

        if ($venta->estado !== VentaEstadoEnum::CONFIRMADA) {
            abort(422, 'Solo se pueden anular ventas confirmadas.');
        }

        foreach ($venta->detalle as $detalle) {
            $articulo = Articulo::query()->lockForUpdate()->findOrFail($detalle->articulo_id);
            ($this->descontarStockArticulo)($articulo, $venta->sucursal_id, (float) $detalle->cantidad, 'sumar');
            ($this->restituirLotesVenta)($detalle);

            $saldoAnterior = $this->kardexService->ultimoSaldo($articulo->id, $venta->sucursal_id);
            $saldoNuevo = round($saldoAnterior + (float) $detalle->cantidad, 2);

            $this->kardexService->registrar([
                'articulo_id' => $articulo->id,
                'sucursal_id' => $venta->sucursal_id,
                'tipo_movimiento' => TipoMovimientoKardexEnum::ANULACION_VENTA->value,
                'referencia_tipo' => ReferenciaKardexEnum::VENTA->value,
                'referencia_id' => $venta->id,
                'fecha' => now(),
                'entrada' => round((float) $detalle->cantidad, 2),
                'salida' => 0,
                'saldo' => $saldoNuevo,
                'costo_unitario' => round((float) $detalle->costo_unitario, 2),
                'costo_total' => round((float) $detalle->costo_total, 2),
                'stock_anterior' => $saldoAnterior,
                'stock_nuevo' => $saldoNuevo,
                'observacion' => $observacion ?: "Anulacion de venta {$venta->numero_venta}",
                'user_id' => $venta->user_id,
            ]);
        }
    }
}
