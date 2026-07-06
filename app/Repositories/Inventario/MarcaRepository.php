<?php

namespace App\Repositories\Inventario;

use App\Models\Marca;
use Illuminate\Database\Eloquent\Collection;

class MarcaRepository
{
    public function allForIndex(): Collection
    {
        return Marca::query()
            ->withCount('articulos')
            ->orderBy('nombre')
            ->get();
    }

    public function activeOptions(): Collection
    {
        return Marca::query()
            ->where('estado', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);
    }

    public function create(array $data): Marca
    {
        return Marca::query()->create($data);
    }

    public function update(Marca $marca, array $data): Marca
    {
        $marca->update($data);

        return $marca->refresh();
    }

    public function delete(Marca $marca): void
    {
        $marca->delete();
    }
}
