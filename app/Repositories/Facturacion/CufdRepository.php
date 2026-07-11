<?php

namespace App\Repositories\Facturacion;

use App\Models\Cufd;
use App\Models\User;
use App\Support\OperationalContextScope;
use Illuminate\Database\Eloquent\Collection;

class CufdRepository
{
    public function allForIndex(array $filters = [], ?User $user = null): Collection
    {
        $query = Cufd::query()
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
            ->when(
                filled($filters['estado'] ?? null),
                fn ($query) => $query->where('estado', filter_var($filters['estado'], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $filters['estado']),
            )
            ->orderByDesc('fecha_vigencia')
            ->orderByDesc('id')
            ->get();
    }

    public function create(array $data): Cufd
    {
        return Cufd::query()->create($data);
    }

    public function refresh(Cufd $cufd): Cufd
    {
        return $cufd->refresh()->load(['sucursal:id,codigo,nombre', 'puntoVenta:id,sucursal_id,codigo,nombre', 'user:id,name,email']);
    }

    public function desactivarContexto(int $sucursalId, int $puntoVentaId, string $ambiente): void
    {
        Cufd::query()
            ->where('sucursal_id', $sucursalId)
            ->where('punto_venta_id', $puntoVentaId)
            ->where('ambiente_facturacion', $ambiente)
            ->update(['estado' => false]);
    }

    public function desactivarVencidos(int $sucursalId, int $puntoVentaId, string $ambiente): void
    {
        Cufd::query()
            ->where('sucursal_id', $sucursalId)
            ->where('punto_venta_id', $puntoVentaId)
            ->where('ambiente_facturacion', $ambiente)
            ->where('estado', true)
            ->whereNotNull('fecha_vigencia')
            ->where('fecha_vigencia', '<', now())
            ->update(['estado' => false]);
    }

    public function desactivarNoUsables(int $sucursalId, int $puntoVentaId, string $ambiente): void
    {
        Cufd::query()
            ->where('sucursal_id', $sucursalId)
            ->where('punto_venta_id', $puntoVentaId)
            ->where('ambiente_facturacion', $ambiente)
            ->where('estado', true)
            ->where(function ($query) {
                $query->whereNull('codigo')
                    ->orWhere('codigo', '')
                    ->orWhereNull('codigo_control')
                    ->orWhere('codigo_control', '')
                    ->orWhereIn('codigo_respuesta', ['SIAT_OBSERVADA', 'SOAP_FAULT', 'SOAP_OPERATION_NOT_CONFIGURED']);
            })
            ->update(['estado' => false]);
    }

    public function vigente(int $sucursalId, int $puntoVentaId, ?string $ambiente = null): ?Cufd
    {
        return $this->usableQuery(Cufd::query())
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

    public function vigenteDelDia(int $sucursalId, int $puntoVentaId, ?string $ambiente = null): ?Cufd
    {
        return $this->usableQuery(Cufd::query())
            ->where('sucursal_id', $sucursalId)
            ->where('punto_venta_id', $puntoVentaId)
            ->when($ambiente !== null, fn ($query) => $query->where('ambiente_facturacion', $ambiente))
            ->where('estado', true)
            ->whereDate('created_at', today())
            ->latest('fecha_vigencia')
            ->latest('id')
            ->first();
    }

    public function vigenteEnFecha(int $sucursalId, int $puntoVentaId, string $ambiente, \Carbon\CarbonInterface $fecha): ?Cufd
    {
        return $this->usableQuery(Cufd::query())
            ->where('sucursal_id', $sucursalId)
            ->where('punto_venta_id', $puntoVentaId)
            ->where('ambiente_facturacion', $ambiente)
            ->where('created_at', '<=', $fecha)
            ->where(function ($query) use ($fecha) {
                $query->whereNull('fecha_vigencia')
                    ->orWhere('fecha_vigencia', '>=', $fecha);
            })
            ->orderByDesc('fecha_vigencia')
            ->orderByDesc('id')
            ->first();
    }

    private function usableQuery($query)
    {
        return $query
            ->whereNotNull('codigo')
            ->where('codigo', '<>', '')
            ->whereNotNull('codigo_control')
            ->where('codigo_control', '<>', '')
            ->where(function ($innerQuery) {
                $innerQuery->whereNull('codigo_respuesta')
                    ->orWhere('codigo_respuesta', '')
                    ->orWhereNotIn('codigo_respuesta', ['SIAT_OBSERVADA', 'SOAP_FAULT', 'SOAP_OPERATION_NOT_CONFIGURED']);
            });
    }
}
