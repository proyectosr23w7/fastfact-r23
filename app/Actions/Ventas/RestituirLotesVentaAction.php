<?php

namespace App\Actions\Ventas;

use App\Models\VentaDetalle;
use App\Repositories\Inventario\ArticuloLoteRepository;

class RestituirLotesVentaAction
{
    public function __construct(
        private readonly ArticuloLoteRepository $articuloLoteRepository,
    ) {
    }

    public function __invoke(VentaDetalle $detalle): void
    {
        foreach ($detalle->lotes as $loteDetalle) {
            if (! $loteDetalle->articulo_lote_id) {
                continue;
            }

            $lote = $this->articuloLoteRepository->findById((int) $loteDetalle->articulo_lote_id);

            if (! $lote) {
                continue;
            }

            $this->articuloLoteRepository->update($lote, [
                'cantidad_actual' => round((float) $lote->cantidad_actual + (float) $loteDetalle->cantidad, 2),
                'estado' => true,
            ]);
        }
    }
}
