<?php

namespace App\Repositories\Facturacion;

use App\Models\Cafc;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class CafcRepository
{
    public function allForIndex(array $filters = []): Collection
    {
        return Cafc::query()
            ->with([
                'sucursal:id,codigo,nombre',
                'puntoVenta:id,sucursal_id,codigo,nombre',
                'user:id,name,email',
            ])
            ->withCount([
                'facturas as total_facturas_count',
                'facturas as facturas_sincronizadas_count' => fn (Builder $query) => $query->where('estado_sincronizacion', 'sincronizada'),
                'facturas as facturas_pendientes_count' => fn (Builder $query) => $query->where('estado_sincronizacion', '<>', 'sincronizada'),
            ])
            ->withMax('facturas as ultimo_numero_factura', 'numero_factura')
            ->when(
                filled($filters['sucursal_id'] ?? null),
                fn (Builder $query) => $query->where('sucursal_id', $filters['sucursal_id']),
            )
            ->when(
                filled($filters['punto_venta_id'] ?? null),
                fn (Builder $query) => $query->where('punto_venta_id', $filters['punto_venta_id']),
            )
            ->when(
                filled($filters['ambiente_facturacion'] ?? null),
                fn (Builder $query) => $query->where('ambiente_facturacion', $filters['ambiente_facturacion']),
            )
            ->when(
                filled($filters['estado'] ?? null),
                fn (Builder $query) => $query->where('estado', filter_var($filters['estado'], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $filters['estado']),
            )
            ->orderByDesc('estado')
            ->orderByDesc('fecha_fin_vigencia')
            ->orderByDesc('id')
            ->get();
    }

    public function create(array $data): Cafc
    {
        return Cafc::query()->create($data);
    }

    public function update(Cafc $cafc, array $data): Cafc
    {
        $cafc->update($data);

        return $this->refresh($cafc);
    }

    public function refresh(Cafc $cafc): Cafc
    {
        return $cafc->refresh()->load([
            'sucursal',
            'puntoVenta',
            'user',
        ])->loadCount([
            'facturas as total_facturas_count',
            'facturas as facturas_sincronizadas_count' => fn (Builder $query) => $query->where('estado_sincronizacion', 'sincronizada'),
            'facturas as facturas_pendientes_count' => fn (Builder $query) => $query->where('estado_sincronizacion', '<>', 'sincronizada'),
        ])->loadMax('facturas as ultimo_numero_factura', 'numero_factura');
    }

    public function vigenteParaContexto(
        int $sucursalId,
        int $puntoVentaId,
        string $ambiente,
        ?Carbon $fecha = null,
        ?int $cafcId = null,
    ): ?Cafc {
        $fecha = ($fecha ?: now(config('app.timezone')))->copy()->timezone(config('app.timezone'));

        return Cafc::query()
            ->when($cafcId, fn (Builder $query) => $query->whereKey($cafcId))
            ->where('estado', true)
            ->where('sucursal_id', $sucursalId)
            ->where('punto_venta_id', $puntoVentaId)
            ->where('ambiente_facturacion', $ambiente)
            ->where(function (Builder $query) use ($fecha) {
                $query->whereNull('fecha_inicio_vigencia')
                    ->orWhere('fecha_inicio_vigencia', '<=', $fecha);
            })
            ->where(function (Builder $query) use ($fecha) {
                $query->whereNull('fecha_fin_vigencia')
                    ->orWhere('fecha_fin_vigencia', '>=', $fecha);
            })
            ->latest('fecha_fin_vigencia')
            ->latest('id')
            ->first();
    }
}
