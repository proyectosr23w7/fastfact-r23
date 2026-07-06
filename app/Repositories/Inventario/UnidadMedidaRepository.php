<?php

namespace App\Repositories\Inventario;

use App\Models\UnidadMedida;
use Illuminate\Database\Eloquent\Collection;

class UnidadMedidaRepository
{
    public function allForIndex(): Collection
    {
        return UnidadMedida::query()
            ->withCount('articulos')
            ->orderBy('nombre')
            ->get();
    }

    public function activeOptions(): Collection
    {
        return UnidadMedida::query()
            ->where('estado', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'abreviatura']);
    }

    public function create(array $data): UnidadMedida
    {
        return UnidadMedida::query()->create($data);
    }

    public function update(UnidadMedida $unidadMedida, array $data): UnidadMedida
    {
        $unidadMedida->update($data);

        return $unidadMedida->refresh();
    }

    public function delete(UnidadMedida $unidadMedida): void
    {
        $unidadMedida->delete();
    }
}
