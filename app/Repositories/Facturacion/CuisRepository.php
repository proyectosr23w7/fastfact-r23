<?php

namespace App\Repositories\Facturacion;

use App\Models\Cuis;
use App\Models\User;
use App\Support\OperationalContextScope;
use Illuminate\Database\Eloquent\Collection;

class CuisRepository
{
    public function allForIndex(array $filters = [], ?User $user = null): Collection
    {
        $query = Cuis::query()
            ->with(['sucursal:id,codigo,nombre', 'puntoVenta:id,sucursal_id,codigo,nombre', 'user:id,name,email']);

        OperationalContextScope::apply($query, $user);
        $filters = OperationalContextScope::mergeFilters($filters, $user);

        return $query
            ->when(
                filled($filters['sucursal_id'] ?? null),
                fn ($query) => $query->where('sucursal_id', $filters['sucursal_id']),
            )
            ->when(
                filled($filters['punto_venta_id'] ?? null),
                fn ($query) => $query->where('punto_venta_id', $filters['punto_venta_id']),
            )
            ->when(
                filled($filters['ambiente_facturacion'] ?? null),
                fn ($query) => $query->where('ambiente_facturacion', $filters['ambiente_facturacion']),
            )
            ->orderByDesc('fecha_vigencia')
            ->orderByDesc('id')
            ->get();
    }

    public function create(array $data): Cuis
    {
        return Cuis::query()->create($data);
    }

    public function refresh(Cuis $cuis): Cuis
    {
        return $cuis->refresh()->load(['sucursal:id,codigo,nombre', 'puntoVenta:id,sucursal_id,codigo,nombre', 'user:id,name,email']);
    }

    public function desactivarContexto(int $sucursalId, int $puntoVentaId, string $ambiente): void
    {
        Cuis::query()
            ->where('sucursal_id', $sucursalId)
            ->where('punto_venta_id', $puntoVentaId)
            ->where('ambiente_facturacion', $ambiente)
            ->update(['estado' => false]);
    }

    public function desactivarVencidos(int $sucursalId, int $puntoVentaId, string $ambiente): void
    {
        Cuis::query()
            ->where('sucursal_id', $sucursalId)
            ->where('punto_venta_id', $puntoVentaId)
            ->where('ambiente_facturacion', $ambiente)
            ->where('estado', true)
            ->whereNotNull('fecha_vigencia')
            ->where('fecha_vigencia', '<', now())
            ->update(['estado' => false]);
    }

    public function vigente(int $sucursalId, int $puntoVentaId, ?string $ambiente = null): ?Cuis
    {
        return Cuis::query()
            ->where('sucursal_id', $sucursalId)
            ->where('punto_venta_id', $puntoVentaId)
            ->when($ambiente !== null, fn ($query) => $query->where('ambiente_facturacion', $ambiente))
            ->where('estado', true)
            ->where(function ($query) {
                $query->whereNull('fecha_vigencia')
                    ->orWhere('fecha_vigencia', '>=', now());
            })
            ->latest('fecha_vigencia')
            ->latest('id')
            ->first();
    }
}
