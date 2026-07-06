<?php

namespace App\Repositories\Ventas;

use App\Models\VentaDetalle;
use App\Models\VentaDetalleLote;
use Illuminate\Support\Collection;

class VentaDetalleLoteRepository
{
    public function createMany(VentaDetalle $detalle, array $items): Collection
    {
        $detalle->lotes()->createMany($items);

        return $detalle->lotes()->get();
    }

    public function deleteByDetalle(VentaDetalle $detalle): void
    {
        $detalle->lotes()->delete();
    }
}
