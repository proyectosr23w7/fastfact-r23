<?php

namespace App\Repositories\Ventas;

use App\Models\VentaCabecera;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class VentaCabeceraRepository
{
    public function allForIndex(array $filters = []): LengthAwarePaginator
    {
        return $this->indexQuery($filters)
            ->with([
                'cliente:id,nombre,razon_social,nit_ci',
                'sucursal:id,codigo,nombre',
                'puntoVenta:id,sucursal_id,codigo,nombre',
                'user:id,name,email',
                'factura:id,venta_id,numero_factura,estado_factura,codigo_emision,evento_significativo_id',
            ])
            ->orderByDesc('fecha_venta')
            ->orderByDesc('id')
            ->paginate(
                perPage: min(max((int) ($filters['per_page'] ?? 10), 5), 100),
                page: max((int) ($filters['page'] ?? 1), 1),
            );
    }

    public function summary(): array
    {
        $today = now()->toDateString();

        $todaySummary = VentaCabecera::query()
            ->whereDate('fecha_venta', $today)
            ->selectRaw('COUNT(*) as cantidad, COALESCE(SUM(total), 0) as total')
            ->first();

        $byState = VentaCabecera::query()
            ->selectRaw('estado, COUNT(*) as cantidad, COALESCE(SUM(total), 0) as total')
            ->groupBy('estado')
            ->get()
            ->keyBy(fn ($row) => is_string($row->estado) ? $row->estado : $row->estado?->value);

        $invoiced = VentaCabecera::query()
            ->whereHas('factura', fn (Builder $query) => $query->whereNotIn('estado_factura', ['anulada', 'rechazada']))
            ->selectRaw('COUNT(*) as cantidad, COALESCE(SUM(total), 0) as total')
            ->first();

        return [
            'hoy' => [
                'cantidad' => (int) ($todaySummary?->cantidad ?? 0),
                'total' => (float) ($todaySummary?->total ?? 0),
            ],
            'pendientes' => [
                'cantidad' => (int) ($byState->get('borrador')?->cantidad ?? 0),
                'total' => (float) ($byState->get('borrador')?->total ?? 0),
            ],
            'confirmadas' => [
                'cantidad' => (int) ($byState->get('confirmada')?->cantidad ?? 0),
                'total' => (float) ($byState->get('confirmada')?->total ?? 0),
            ],
            'facturadas' => [
                'cantidad' => (int) ($invoiced?->cantidad ?? 0),
                'total' => (float) ($invoiced?->total ?? 0),
            ],
        ];
    }

    private function indexQuery(array $filters): Builder
    {
        return VentaCabecera::query()
            ->when(filled($filters['search'] ?? null), function (Builder $query) use ($filters): void {
                $search = trim((string) $filters['search']);

                $query->where(function (Builder $nested) use ($search): void {
                    $nested
                        ->where('numero_venta', 'like', "%{$search}%")
                        ->orWhereHas('cliente', fn (Builder $client) => $client
                            ->where('nombre', 'like', "%{$search}%")
                            ->orWhere('razon_social', 'like', "%{$search}%")
                            ->orWhere('nit_ci', 'like', "%{$search}%"));
                });
            })
            ->when(filled($filters['fecha_desde'] ?? null), fn (Builder $query) => $query->whereDate('fecha_venta', '>=', $filters['fecha_desde']))
            ->when(filled($filters['fecha_hasta'] ?? null), fn (Builder $query) => $query->whereDate('fecha_venta', '<=', $filters['fecha_hasta']))
            ->when(filled($filters['cliente_id'] ?? null), fn (Builder $query) => $query->where('cliente_id', $filters['cliente_id']))
            ->when(filled($filters['sucursal_id'] ?? null), fn (Builder $query) => $query->where('sucursal_id', $filters['sucursal_id']))
            ->when(filled($filters['punto_venta_id'] ?? null), fn (Builder $query) => $query->where('punto_venta_id', $filters['punto_venta_id']))
            ->when(filled($filters['estado'] ?? null), fn (Builder $query) => $query->where('estado', $filters['estado']))
            ->when(filled($filters['tipo_documento_venta'] ?? null), fn (Builder $query) => $query->where('tipo_documento_venta', $filters['tipo_documento_venta']));
    }

    public function create(array $data): VentaCabecera
    {
        return VentaCabecera::query()->create($data);
    }

    public function update(VentaCabecera $venta, array $data): VentaCabecera
    {
        $venta->update($data);

        return $this->refresh($venta);
    }

    public function refresh(VentaCabecera $venta): VentaCabecera
    {
        return $venta->refresh()->load([
            'cliente:id,nombre,razon_social,nit_ci',
            'sucursal:id,codigo,nombre',
            'puntoVenta:id,sucursal_id,codigo,nombre',
            'user:id,name,email',
            'detalle.articulo:id,nombre,codigo_generico,costo,stock_actual',
            'detalle.lotes',
                'factura:id,venta_id,numero_factura,estado_factura,codigo_emision,evento_significativo_id',
        ]);
    }

    public function findForProcess(VentaCabecera $venta): VentaCabecera
    {
        return VentaCabecera::query()
            ->with([
                'cliente',
                'sucursal',
                'puntoVenta',
                'user',
                'detalle.articulo',
                'detalle.lotes',
                'factura',
            ])
            ->lockForUpdate()
            ->findOrFail($venta->id);
    }

    public function getNextNumber(): string
    {
        $nextId = (int) VentaCabecera::query()->max('id') + 1;

        return 'VTA-'.str_pad((string) $nextId, 8, '0', STR_PAD_LEFT);
    }
}
