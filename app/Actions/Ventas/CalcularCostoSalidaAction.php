<?php

namespace App\Actions\Ventas;

use App\Enums\MetodoSalidaEnum;
use App\Models\Articulo;
use App\Models\ArticuloStock;
use App\Repositories\Inventario\ArticuloLoteRepository;

class CalcularCostoSalidaAction
{
    public function __construct(
        private readonly ArticuloLoteRepository $articuloLoteRepository,
    ) {
    }

    public function __invoke(
        Articulo $articulo,
        int $sucursalId,
        float $cantidad,
        string $metodoSalida,
        bool $soloValidar = false,
    ): array {
        $stockSucursal = ArticuloStock::query()
            ->where('articulo_id', $articulo->id)
            ->where('sucursal_id', $sucursalId)
            ->value('stock_actual');

        if ((float) $stockSucursal < $cantidad) {
            abort(422, "Stock insuficiente para {$articulo->nombre} en la sucursal seleccionada.");
        }

        $consumos = $this->resolverConsumosPorCapas($articulo->id, $sucursalId, $cantidad, $metodoSalida);

        if ($soloValidar) {
            return [
                'costo_unitario' => 0,
                'costo_total' => 0,
                'consumos' => $consumos,
            ];
        }

        $costoTotal = round((float) collect($consumos)->sum('costo_total'), 2);
        $costoUnitario = $cantidad > 0 ? round($costoTotal / $cantidad, 2) : 0;

        return [
            'costo_unitario' => $costoUnitario,
            'costo_total' => $costoTotal,
            'consumos' => $consumos,
        ];
    }

    private function resolverConsumosPorCapas(int $articuloId, int $sucursalId, float $cantidad, string $metodoSalida): array
    {
        $lotes = $this->articuloLoteRepository->availableForSalida($articuloId, $sucursalId, $metodoSalida);
        $restante = round($cantidad, 2);
        $consumos = [];

        foreach ($lotes as $lote) {
            if ($restante <= 0) {
                break;
            }

            $disponible = round((float) $lote->cantidad_actual, 2);

            if ($disponible <= 0) {
                continue;
            }

            $cantidadConsumida = min($restante, $disponible);
            $consumos[] = [
                'articulo_lote_id' => $lote->id,
                'lote' => $lote->lote,
                'cantidad' => round($cantidadConsumida, 2),
                'costo_unitario' => round((float) $lote->costo_unitario, 2),
                'costo_total' => round($cantidadConsumida * (float) $lote->costo_unitario, 2),
                'fecha_vencimiento' => optional($lote->fecha_vencimiento)?->format('Y-m-d'),
            ];
            $restante = round($restante - $cantidadConsumida, 2);
        }

        if ($restante > 0) {
            if ($metodoSalida === MetodoSalidaEnum::POR_LOTE->value) {
                abort(422, 'No existe stock trazable suficiente por lote para confirmar la venta.');
            }

            $consumos[] = [
                'articulo_lote_id' => null,
                'lote' => 'COSTO_REFERENCIAL',
                'cantidad' => round($restante, 2),
                'costo_unitario' => round((float) Articulo::query()->whereKey($articuloId)->value('costo'), 2),
                'costo_total' => round($restante * (float) Articulo::query()->whereKey($articuloId)->value('costo'), 2),
                'fecha_vencimiento' => null,
            ];
        }

        return $consumos;
    }
}
