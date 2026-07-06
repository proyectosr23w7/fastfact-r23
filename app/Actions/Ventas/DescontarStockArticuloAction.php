<?php

namespace App\Actions\Ventas;

use App\Models\Articulo;
use App\Models\ArticuloStock;

class DescontarStockArticuloAction
{
    public function __invoke(Articulo $articulo, int $sucursalId, float $cantidad, string $operacion): Articulo
    {
        $stockSucursal = ArticuloStock::query()->firstOrCreate(
            [
                'articulo_id' => $articulo->id,
                'sucursal_id' => $sucursalId,
            ],
            [
                'stock_actual' => 0,
            ],
        );

        $stockActualSucursal = (float) $stockSucursal->stock_actual;
        $nuevoStockSucursal = $operacion === 'sumar'
            ? round($stockActualSucursal + $cantidad, 2)
            : round($stockActualSucursal - $cantidad, 2);

        if ($nuevoStockSucursal < 0) {
            abort(422, "El articulo {$articulo->nombre} quedaria con stock negativo en la sucursal.");
        }

        $stockSucursal->update([
            'stock_actual' => $nuevoStockSucursal,
        ]);

        $articulo->update([
            'stock_actual' => round((float) $articulo->stocks()->sum('stock_actual'), 2),
        ]);

        return $articulo->refresh();
    }
}
