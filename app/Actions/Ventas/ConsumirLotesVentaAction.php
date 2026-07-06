<?php

namespace App\Actions\Ventas;

use App\Models\VentaDetalle;
use App\Repositories\Inventario\ArticuloLoteRepository;
use App\Services\Ventas\VentaDetalleLoteService;

class ConsumirLotesVentaAction
{
    public function __construct(
        private readonly ArticuloLoteRepository $articuloLoteRepository,
        private readonly VentaDetalleLoteService $ventaDetalleLoteService,
    ) {
    }

    public function __invoke(VentaDetalle $detalle, array $consumos): array
    {
        $lotesDetalle = [];

        foreach ($consumos as $consumo) {
            if (! empty($consumo['articulo_lote_id'])) {
                $lote = $this->articuloLoteRepository->findById((int) $consumo['articulo_lote_id']);

                if (! $lote || (float) $lote->cantidad_actual < (float) $consumo['cantidad']) {
                    abort(422, 'No existe stock suficiente en lotes para confirmar la venta.');
                }

                $this->articuloLoteRepository->update($lote, [
                    'cantidad_actual' => round((float) $lote->cantidad_actual - (float) $consumo['cantidad'], 2),
                    'estado' => round((float) $lote->cantidad_actual - (float) $consumo['cantidad'], 2) > 0,
                ]);
            }

            $lotesDetalle[] = [
                'articulo_id' => $detalle->articulo_id,
                'articulo_lote_id' => $consumo['articulo_lote_id'],
                'lote' => $consumo['lote'],
                'cantidad' => $consumo['cantidad'],
                'costo_unitario' => $consumo['costo_unitario'],
                'costo_total' => $consumo['costo_total'],
                'fecha_vencimiento' => $consumo['fecha_vencimiento'],
            ];
        }

        $this->ventaDetalleLoteService->limpiar($detalle);

        if ($lotesDetalle !== []) {
            $this->ventaDetalleLoteService->registrarConsumo($detalle, $lotesDetalle);
        }

        return $lotesDetalle;
    }
}
