<?php

namespace App\Services\Inventario;

use App\Models\Articulo;
use App\Repositories\Inventario\ArticuloLoteRepository;

class ArticuloLoteService
{
    public function __construct(
        private readonly ArticuloLoteRepository $repository,
    ) {
    }

    public function registrarIngreso(
        Articulo $articulo,
        int $sucursalId,
        float $cantidad,
        float $costoUnitario,
        ?string $lote,
        ?string $fechaVencimiento,
    ): void {
        $lotKey = filled($lote) ? trim((string) $lote) : 'SIN-LOTE';
        $existingLot = $this->repository->findByArticuloSucursalLote($articulo->id, $sucursalId, $lotKey);

        if ($existingLot) {
            $this->repository->update($existingLot, [
                'cantidad_inicial' => (float) $existingLot->cantidad_inicial + $cantidad,
                'cantidad_actual' => (float) $existingLot->cantidad_actual + $cantidad,
                'costo_unitario' => $costoUnitario,
                'fecha_vencimiento' => $fechaVencimiento,
                'estado' => true,
            ]);

            return;
        }

        $this->repository->create([
            'articulo_id' => $articulo->id,
            'sucursal_id' => $sucursalId,
            'lote' => $lotKey,
            'fecha_vencimiento' => $fechaVencimiento,
            'cantidad_inicial' => $cantidad,
            'cantidad_actual' => $cantidad,
            'costo_unitario' => $costoUnitario,
            'estado' => true,
        ]);
    }

    public function revertirIngreso(
        Articulo $articulo,
        int $sucursalId,
        float $cantidad,
        ?string $lote,
    ): void {
        $lotKey = filled($lote) ? trim((string) $lote) : 'SIN-LOTE';
        $existingLot = $this->repository->findByArticuloSucursalLote($articulo->id, $sucursalId, $lotKey);

        if (! $existingLot || (float) $existingLot->cantidad_actual < $cantidad) {
            abort(422, 'No existe stock suficiente en lotes para revertir el ingreso.');
        }

        $newQuantity = (float) $existingLot->cantidad_actual - $cantidad;

        $this->repository->update($existingLot, [
            'cantidad_actual' => $newQuantity,
            'estado' => $newQuantity > 0,
        ]);
    }

    public function costoReferencial(int $articuloId): float
    {
        $lots = $this->repository->activeForArticulo($articuloId);
        $totalQuantity = (float) $lots->sum('cantidad_actual');

        if ($totalQuantity <= 0) {
            return 0;
        }

        $totalValue = (float) $lots->sum(
            fn ($lot) => (float) $lot->cantidad_actual * (float) $lot->costo_unitario,
        );

        return round($totalValue / $totalQuantity, 6);
    }
}
