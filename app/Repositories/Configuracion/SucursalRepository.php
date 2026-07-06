<?php

namespace App\Repositories\Configuracion;

use App\Models\Configuracion\Sucursal;
use Illuminate\Database\Eloquent\Collection;

class SucursalRepository
{
    public function existsByCodigo(int $codigo, ?int $ignoreId = null): bool
    {
        return Sucursal::query()
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->where('codigo', $codigo)
            ->exists();
    }

    public function allForIndex(): Collection
    {
        return Sucursal::query()
            ->withCount('puntosVenta')
            ->orderBy('codigo')
            ->get();
    }

    public function create(array $data): Sucursal
    {
        return Sucursal::query()->create($data);
    }

    public function update(Sucursal $sucursal, array $data): Sucursal
    {
        $sucursal->update($data);

        return $sucursal->refresh();
    }

    public function delete(Sucursal $sucursal): void
    {
        $sucursal->delete();
    }
}
