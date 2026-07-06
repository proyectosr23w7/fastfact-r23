<?php

namespace App\Repositories\Facturacion;

use App\Models\SiatSincronizacion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema;

class SiatSyncRepository
{
    public function allForIndex(?int $codigoSucursal = null, ?int $codigoPuntoVenta = null): Collection
    {
        if (! Schema::hasTable('siat_sincronizaciones')) {
            return new Collection();
        }

        return SiatSincronizacion::query()
            ->with('user:id,name,email')
            ->when($codigoSucursal !== null, fn ($query) => $query->where('codigo_sucursal', $codigoSucursal))
            ->when($codigoPuntoVenta !== null, fn ($query) => $query->where('codigo_punto_venta', $codigoPuntoVenta))
            ->orderByDesc('fecha_sincronizacion')
            ->orderByDesc('id')
            ->get();
    }

    public function create(array $data): SiatSincronizacion
    {
        return SiatSincronizacion::query()->create($data);
    }

    public function syncCatalog(string $modelClass, array $records, array $uniqueBy, array $updatable): void
    {
        if ($records === []) {
            return;
        }

        $modelClass::query()->upsert($records, $uniqueBy, $updatable);
    }

    public function replaceCatalog(string $modelClass, array $records): void
    {
        $modelClass::query()->truncate();

        if ($records === []) {
            return;
        }

        $modelClass::query()->insert($records);
    }
}
