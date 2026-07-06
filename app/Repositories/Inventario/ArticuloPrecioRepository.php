<?php

namespace App\Repositories\Inventario;

use App\Models\Articulo;
use App\Models\ArticuloPrecio;
use Illuminate\Database\Eloquent\Collection;

class ArticuloPrecioRepository
{
    public function allForIndex(): Collection
    {
        return ArticuloPrecio::query()
            ->with('articulo:id,nombre,codigo_generico')
            ->orderBy('articulo_id')
            ->orderBy('cantidad_minima')
            ->get();
    }

    public function create(array $data): ArticuloPrecio
    {
        return ArticuloPrecio::query()->create($data);
    }

    public function update(ArticuloPrecio $articuloPrecio, array $data): ArticuloPrecio
    {
        $articuloPrecio->update($data);

        return $articuloPrecio->refresh();
    }

    public function delete(ArticuloPrecio $articuloPrecio): void
    {
        $articuloPrecio->delete();
    }

    public function syncForArticulo(Articulo $articulo, array $precios): void
    {
        $articulo->precios()->delete();

        foreach ($precios as $precio) {
            $articulo->precios()->create($precio);
        }
    }

    public function activeForArticulo(Articulo $articulo): Collection
    {
        return $articulo->precios()
            ->where('estado', true)
            ->orderBy('cantidad_minima')
            ->get();
    }
}
