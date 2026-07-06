<?php

namespace App\Actions\Ventas;

use App\Enums\ReferenciaKardexEnum;
use App\Enums\TipoMovimientoKardexEnum;
use App\Models\Articulo;
use App\Models\VentaCabecera;
use App\Models\VentaDetalle;
use App\Services\Inventario\KardexService;

class RegistrarKardexVentaAction
{
    public function __construct(
        private readonly KardexService $kardexService,
    ) {
    }

    public function __invoke(VentaCabecera $venta, VentaDetalle $detalle, Articulo $articulo, ?string $observacion = null): void
    {
        $saldoAnterior = $this->kardexService->ultimoSaldo($articulo->id, $venta->sucursal_id);
        $salida = round((float) $detalle->cantidad, 2);
        $saldoNuevo = round($saldoAnterior - $salida, 2);

        if ($saldoNuevo < 0) {
            abort(422, "El kardex de {$articulo->nombre} quedaria inconsistente al confirmar la venta.");
        }

        $this->kardexService->registrar([
            'articulo_id' => $articulo->id,
            'sucursal_id' => $venta->sucursal_id,
            'tipo_movimiento' => TipoMovimientoKardexEnum::SALIDA->value,
            'referencia_tipo' => ReferenciaKardexEnum::VENTA->value,
            'referencia_id' => $venta->id,
            'fecha' => $venta->fecha_venta->format('Y-m-d 00:00:00'),
            'entrada' => 0,
            'salida' => $salida,
            'saldo' => $saldoNuevo,
            'costo_unitario' => round((float) $detalle->costo_unitario, 2),
            'costo_total' => round((float) $detalle->costo_total, 2),
            'stock_anterior' => $saldoAnterior,
            'stock_nuevo' => $saldoNuevo,
            'observacion' => $observacion ?: "Salida por venta {$venta->numero_venta}",
            'user_id' => $venta->user_id,
        ]);
    }
}
