<?php

namespace App\Repositories\Facturacion;

use App\Models\EventoSignificativoReporte;
use App\Models\EventoSignificativo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class EventoSignificativoRepository
{
    public function allForIndex(array $filters = []): Collection
    {
        return EventoSignificativo::query()
            ->with([
                'sucursal:id,codigo,nombre',
                'puntoVenta:id,sucursal_id,codigo,nombre',
                'cufdEvento:id,codigo,codigo_control,fecha_vigencia',
                'cufdRecuperacion:id,codigo,codigo_control,fecha_vigencia',
                'cafc' => $this->cafcRelation(),
                'user:id,name,email',
                'facturas:id,evento_significativo_id,evento_significativo_paquete_id,cliente_id,cafc_id,user_id,numero_factura,cuf,codigo_recepcion,codigo_emision,estado_factura,estado_sincronizacion,codigo_estado,descripcion_estado,monto_total,fecha_emision',
                'facturas.cliente:id,nombre,razon_social,nit_ci',
                'facturas.cafc:id,codigo,descripcion',
                'facturas.user:id,name,email',
                'reportes.user:id,name,email',
                'paquetes.cufdEnvio:id,codigo,codigo_control,fecha_vigencia',
                'paquetes.user:id,name,email',
            ])
            ->withCount([
                'facturas as total_facturas_count',
                'facturas as facturas_pendientes_count' => fn (Builder $query) => $query->where('estado_sincronizacion', '<>', 'sincronizada'),
                'facturas as facturas_sincronizadas_count' => fn (Builder $query) => $query->where('estado_sincronizacion', 'sincronizada'),
                'paquetes as total_paquetes_count',
            ])
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
            ->orderByDesc('fecha_inicio')
            ->orderByDesc('id')
            ->get();
    }

    public function create(array $data): EventoSignificativo
    {
        return EventoSignificativo::query()->create($data);
    }

    public function refresh(EventoSignificativo $evento): EventoSignificativo
    {
        return $evento->refresh()
            ->load([
                'sucursal:id,codigo,nombre',
                'puntoVenta:id,sucursal_id,codigo,nombre',
                'cufdEvento:id,codigo,codigo_control,fecha_vigencia',
                'cufdRecuperacion:id,codigo,codigo_control,fecha_vigencia',
                'cafc' => $this->cafcRelation(),
                'user:id,name,email',
                'facturas:id,evento_significativo_id,evento_significativo_paquete_id,cliente_id,cafc_id,user_id,numero_factura,cuf,codigo_recepcion,codigo_emision,estado_factura,estado_sincronizacion,codigo_estado,descripcion_estado,monto_total,fecha_emision',
                'facturas.cliente:id,nombre,razon_social,nit_ci',
                'facturas.cafc:id,codigo,descripcion',
                'facturas.user:id,name,email',
                'reportes.user:id,name,email',
                'paquetes.cufdEnvio:id,codigo,codigo_control,fecha_vigencia',
                'paquetes.user:id,name,email',
            ])
            ->loadCount([
                'facturas as total_facturas_count',
                'facturas as facturas_pendientes_count' => fn (Builder $query) => $query->where('estado_sincronizacion', '<>', 'sincronizada'),
                'facturas as facturas_sincronizadas_count' => fn (Builder $query) => $query->where('estado_sincronizacion', 'sincronizada'),
                'paquetes as total_paquetes_count',
            ]);
    }

    public function update(EventoSignificativo $evento, array $data): EventoSignificativo
    {
        $evento->update($data);

        return $this->refresh($evento);
    }

    public function findForProcess(EventoSignificativo $evento): EventoSignificativo
    {
        return EventoSignificativo::query()
            ->with([
                'sucursal:id,codigo,nombre',
                'puntoVenta:id,sucursal_id,codigo,nombre',
                'cufdEvento:id,codigo,codigo_control,fecha_vigencia',
                'cufdRecuperacion:id,codigo,codigo_control,fecha_vigencia',
                'cafc' => $this->cafcRelation(),
                'user:id,name,email',
                'facturas:id,evento_significativo_id,evento_significativo_paquete_id,cafc_id,fecha_emision,estado_factura,estado_sincronizacion,codigo_recepcion,numero_factura,cuf,xml_fiscal',
                'facturas.cafc:id,codigo',
                'paquetes.cufdEnvio:id,codigo,codigo_control,fecha_vigencia',
                'paquetes.facturas:id,evento_significativo_paquete_id,numero_factura,estado_sincronizacion',
                'reportes.user:id,name,email',
            ])
            ->lockForUpdate()
            ->findOrFail($evento->id);
    }

    public function activeForContext(int $sucursalId, int $puntoVentaId, string $ambiente): ?EventoSignificativo
    {
        return $this->activeQuery()
            ->where('sucursal_id', $sucursalId)
            ->where('punto_venta_id', $puntoVentaId)
            ->where('ambiente_facturacion', $ambiente)
            ->latest('fecha_inicio')
            ->latest('id')
            ->first();
    }

    public function manualPendingForContext(int $sucursalId, int $puntoVentaId, string $ambiente): ?EventoSignificativo
    {
        return $this->operationalFacturableQuery()
            ->where('sucursal_id', $sucursalId)
            ->where('punto_venta_id', $puntoVentaId)
            ->where('ambiente_facturacion', $ambiente)
            ->where('tipo_contingencia', 'manual')
            ->latest('fecha_inicio')
            ->latest('id')
            ->first();
    }

    public function activeEvents(array $filters = []): Collection
    {
        return $this->activeQuery()
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
            ->get();
    }

    public function facturableEvents(array $filters = []): Collection
    {
        return $this->operationalFacturableQuery()
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
            ->get();
    }

    public function createReport(array $data): EventoSignificativoReporte
    {
        return EventoSignificativoReporte::query()->create($data);
    }

    public function latestReports(array $filters = [], int $limit = 20): Collection
    {
        return EventoSignificativoReporte::query()
            ->with([
                'eventoSignificativo:id,codigo_evento,estado',
                'sucursal:id,codigo,nombre',
                'puntoVenta:id,sucursal_id,codigo,nombre',
                'user:id,name,email',
            ])
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
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    private function activeQuery(): Builder
    {
        return EventoSignificativo::query()
            ->with([
                'sucursal:id,codigo,nombre',
                'puntoVenta:id,sucursal_id,codigo,nombre',
                'cufdEvento:id,codigo,codigo_control,fecha_vigencia',
                'cufdRecuperacion:id,codigo,codigo_control,fecha_vigencia',
                'cafc' => $this->cafcRelation(),
                'user:id,name,email',
                'reportes.user:id,name,email',
            ])
            ->where('estado', 'activo_local');
    }

    private function operationalFacturableQuery(): Builder
    {
        return EventoSignificativo::query()
            ->with([
                'sucursal:id,codigo,nombre',
                'puntoVenta:id,sucursal_id,codigo,nombre',
                'cufdEvento:id,codigo,codigo_control,fecha_vigencia',
                'cufdRecuperacion:id,codigo,codigo_control,fecha_vigencia',
                'cafc' => $this->cafcRelation(),
                'user:id,name,email',
            ])
            ->where(function (Builder $query) {
                $query->where('estado', 'activo_local')
                    ->orWhere(function (Builder $subQuery) {
                        $subQuery->where('tipo_contingencia', 'manual')
                            ->where('estado', 'cerrado_local');
                    });
            })
            ->orderByDesc('fecha_inicio')
            ->orderByDesc('id');
    }

    private function cafcRelation(): \Closure
    {
        return static function ($query): void {
            $query->select([
                'id',
                'codigo',
                'pin',
                'descripcion',
                'sucursal_id',
                'punto_venta_id',
                'ambiente_facturacion',
                'fecha_inicio_vigencia',
                'fecha_fin_vigencia',
                'numero_inicial',
                'numero_final',
                'estado',
                'observacion',
                'user_id',
            ])->withCount([
                'facturas as total_facturas_count',
                'facturas as facturas_pendientes_count' => fn (Builder $facturas) => $facturas->where('estado_sincronizacion', '<>', 'sincronizada'),
                'facturas as facturas_sincronizadas_count' => fn (Builder $facturas) => $facturas->where('estado_sincronizacion', 'sincronizada'),
            ])->withMax('facturas as ultimo_numero_factura', 'numero_factura');
        };
    }
}
