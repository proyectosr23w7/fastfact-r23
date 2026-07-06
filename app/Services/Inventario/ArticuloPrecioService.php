<?php

namespace App\Services\Inventario;

use App\Models\Articulo;
use App\Models\ArticuloPrecio;
use App\Models\Configuracion\Configuracion;
use App\Repositories\Inventario\ArticuloPrecioRepository;
use Illuminate\Database\Eloquent\Collection;

class ArticuloPrecioService
{
    public function __construct(
        private readonly ArticuloPrecioRepository $repository,
    ) {
    }

    public function listar(): Collection
    {
        return $this->repository->allForIndex();
    }

    public function syncForArticulo(Articulo $articulo, array $precios): void
    {
        if (! $this->priceFeaturesEnabled()) {
            $articulo->precios()->delete();

            return;
        }

        $normalizedPrices = collect($precios)
            ->filter(fn (array $precio) => filled($precio['precio'] ?? null))
            ->map(fn (array $precio) => [
                'cantidad_minima' => $precio['cantidad_minima'] ?? 1,
                'precio' => $precio['precio'],
                'tipo_precio' => $precio['tipo_precio'] ?? 'general',
                'estado' => $precio['estado'] ?? true,
            ])
            ->sortBy('cantidad_minima')
            ->values()
            ->all();

        $this->repository->syncForArticulo($articulo, $normalizedPrices);
    }

    public function create(array $data): ArticuloPrecio
    {
        return $this->repository->create($data);
    }

    public function update(ArticuloPrecio $articuloPrecio, array $data): ArticuloPrecio
    {
        return $this->repository->update($articuloPrecio, $data);
    }

    public function delete(ArticuloPrecio $articuloPrecio): void
    {
        $this->repository->delete($articuloPrecio);
    }

    public function getApplicablePrice(Articulo $articulo, float $cantidad): ?ArticuloPrecio
    {
        if (! $this->priceFeaturesEnabled()) {
            return null;
        }

        return $this->repository->activeForArticulo($articulo)
            ->where('cantidad_minima', '<=', $cantidad)
            ->sortByDesc('cantidad_minima')
            ->first();
    }

    public function priceFeaturesEnabled(): bool
    {
        $configuracion = Configuracion::query()
            ->where('estado', true)
            ->latest('id')
            ->first();

        return (bool) ($configuracion?->multiples_precios || $configuracion?->precios_por_cantidad);
    }
}
