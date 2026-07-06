<?php

namespace App\Services\Ventas;

use App\Models\VentaDetalle;
use App\Repositories\Ventas\VentaDetalleLoteRepository;
use Illuminate\Support\Collection;

class VentaDetalleLoteService
{
    public function __construct(
        private readonly VentaDetalleLoteRepository $repository,
    ) {
    }

    public function registrarConsumo(VentaDetalle $detalle, array $items): Collection
    {
        return $this->repository->createMany($detalle, $items);
    }

    public function limpiar(VentaDetalle $detalle): void
    {
        $this->repository->deleteByDetalle($detalle);
    }
}
