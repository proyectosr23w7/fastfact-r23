<?php

namespace App\Repositories\Inventario;

use App\Models\Kardex;
use Illuminate\Database\Eloquent\Collection;

class KardexRepository
{
    public function create(array $data): Kardex
    {
        return Kardex::query()->create($data);
    }

    public function lastBalance(int $articuloId, int $sucursalId): float
    {
        return (float) (Kardex::query()
            ->where('articulo_id', $articuloId)
            ->where('sucursal_id', $sucursalId)
            ->latest('id')
            ->value('saldo') ?? 0);
    }

    public function allForIndex(array $filters = []): Collection
    {
        return Kardex::query()
            ->with([
                'articulo:id,nombre,codigo_generico',
                'sucursal:id,codigo,nombre',
                'user:id,name',
            ])
            ->when(
                filled($filters['articulo_id'] ?? null),
                fn ($query) => $query->where('articulo_id', $filters['articulo_id']),
            )
            ->when(
                filled($filters['sucursal_id'] ?? null),
                fn ($query) => $query->where('sucursal_id', $filters['sucursal_id']),
            )
            ->when(
                filled($filters['referencia_tipo'] ?? null),
                fn ($query) => $query->where('referencia_tipo', $filters['referencia_tipo']),
            )
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();
    }

}
