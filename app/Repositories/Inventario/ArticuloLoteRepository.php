<?php

namespace App\Repositories\Inventario;

use App\Models\ArticuloLote;
use Illuminate\Support\Collection;

class ArticuloLoteRepository
{
    public function findByArticuloSucursalLote(int $articuloId, int $sucursalId, string $lote): ?ArticuloLote
    {
        return ArticuloLote::query()
            ->where('articulo_id', $articuloId)
            ->where('sucursal_id', $sucursalId)
            ->where('lote', $lote)
            ->lockForUpdate()
            ->first();
    }

    public function create(array $data): ArticuloLote
    {
        return ArticuloLote::query()->create($data);
    }

    public function update(ArticuloLote $lote, array $data): ArticuloLote
    {
        $lote->update($data);

        return $lote->refresh();
    }

    public function activeForArticulo(int $articuloId): Collection
    {
        return ArticuloLote::query()
            ->where('articulo_id', $articuloId)
            ->where('estado', true)
            ->where('cantidad_actual', '>', 0)
            ->get();
    }

    public function findById(int $id): ?ArticuloLote
    {
        return ArticuloLote::query()->lockForUpdate()->find($id);
    }

    public function availableForSalida(int $articuloId, int $sucursalId, string $metodoSalida): Collection
    {
        $query = ArticuloLote::query()
            ->where('articulo_id', $articuloId)
            ->where('sucursal_id', $sucursalId)
            ->where('estado', true)
            ->where('cantidad_actual', '>', 0);

        return match ($metodoSalida) {
            'ueps' => $query->orderByDesc('created_at')->orderByDesc('id')->get(),
            'por_lote' => $query->orderBy('fecha_vencimiento')->orderBy('created_at')->orderBy('id')->get(),
            default => $query->orderBy('created_at')->orderBy('id')->get(),
        };
    }
}
