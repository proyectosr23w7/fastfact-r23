<?php

namespace App\Repositories\Facturacion;

use App\Models\Factura;
use App\Models\User;
use App\Support\OperationalContextScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class FacturaRepository
{
    public function allForIndex(array $filters = [], ?User $user = null): Collection
    {
        $query = Factura::query()
            ->with([
                'cliente:id,nombre,razon_social,nit_ci',
                'detalles',
                'sucursal:id,codigo,nombre',
                'puntoVenta:id,sucursal_id,codigo,nombre',
                'user:id,name,email',
                'cuis:id,codigo,sucursal_id,punto_venta_id,fecha_vigencia',
                'cufd:id,codigo,codigo_control,sucursal_id,punto_venta_id,fecha_vigencia',
                'cafc:id,codigo,descripcion,sucursal_id,punto_venta_id',
                'eventoSignificativo:id,codigo_evento,descripcion,fecha_inicio,fecha_fin,estado',
                'anulaciones.user:id,name,email',
            ]);

        OperationalContextScope::apply($query, $user);
        $this->applyFilters($query, OperationalContextScope::mergeFilters($filters, $user));

        return $query
            ->when(
                ($filters['scope'] ?? '') === 'pendientes',
                fn (Builder $query) => $query->whereIn('estado_factura', ['pendiente', 'pendiente_envio']),
            )
            ->when(
                ($filters['scope'] ?? '') === 'contingencias',
                fn (Builder $query) => $query->where('codigo_emision', 2),
            )
            ->when(
                filled($filters['estado_factura'] ?? null),
                fn (Builder $query) => $query->where('estado_factura', $filters['estado_factura']),
            )
            ->orderByDesc('fecha_emision')
            ->orderByDesc('id')
            ->get();
    }

    public function operationalSummary(array $filters = [], ?User $user = null): array
    {
        $base = Factura::query();
        OperationalContextScope::apply($base, $user);
        $this->applyFilters($base, OperationalContextScope::mergeFilters($filters, $user));

        $summary = function (callable $constraint) use ($base): array {
            $query = clone $base;
            $constraint($query);

            return [
                'count' => (int) (clone $query)->count(),
                'amount' => (float) (clone $query)->sum('monto_total'),
            ];
        };

        return [
            'emitidas_hoy' => $summary(fn (Builder $query) => $query
                ->whereDate('fecha_emision', now(config('app.timezone'))->toDateString())
                ->where('estado_factura', 'emitida')),
            'pendientes' => $summary(fn (Builder $query) => $query
                ->whereIn('estado_factura', ['pendiente', 'pendiente_envio'])),
            'con_observaciones' => $summary(fn (Builder $query) => $query
                ->whereIn('estado_factura', ['observada', 'rechazada'])),
            'anuladas' => $summary(fn (Builder $query) => $query
                ->where('estado_factura', 'anulada')),
            'contingencias' => $summary(fn (Builder $query) => $query
                ->where('codigo_emision', 2)),
        ];
    }

    public function create(array $data): Factura
    {
        return Factura::query()->create($data);
    }

    public function update(Factura $factura, array $data): Factura
    {
        $factura->update($data);

        return $this->refresh($factura);
    }

    public function refresh(Factura $factura): Factura
    {
        return $factura->refresh()->load([
            'detalles.articulo.unidadMedida',
            'cliente',
            'sucursal',
            'puntoVenta',
            'user',
            'cuis',
            'cufd',
            'cafc',
            'eventoSignificativo',
            'anulaciones.user',
        ]);
    }

    public function findForProcess(Factura $factura): Factura
    {
        return Factura::query()
            ->with([
                'detalles.articulo.unidadMedida',
                'cliente',
                'sucursal',
                'puntoVenta',
                'user',
                'cuis',
                'cufd',
                'cafc',
                'eventoSignificativo',
                'anulaciones.user',
            ])
            ->lockForUpdate()
            ->findOrFail($factura->id);
    }

    public function getNextInvoiceNumber(int $sucursalId, int $puntoVentaId, string $ambienteFacturacion = 'piloto', int $tipoFacturacion = 0): int
    {
        $ambienteFacturacion = $ambienteFacturacion ?: 'piloto';
        $ultimoNumeroExistente = (int) Factura::query()
            ->whereNull('cafc_id')
            ->where('sucursal_id', $sucursalId)
            ->where('punto_venta_id', $puntoVentaId)
            ->where('ambiente_facturacion', $ambienteFacturacion)
            ->where('tipo_facturacion', $tipoFacturacion)
            ->max('numero_factura');

        DB::table('factura_correlativos')->insertOrIgnore([
            'sucursal_id' => $sucursalId,
            'punto_venta_id' => $puntoVentaId,
            'ambiente_facturacion' => $ambienteFacturacion,
            'tipo_facturacion' => $tipoFacturacion,
            'ultimo_numero' => $ultimoNumeroExistente,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $correlativo = DB::table('factura_correlativos')
            ->where('sucursal_id', $sucursalId)
            ->where('punto_venta_id', $puntoVentaId)
            ->where('ambiente_facturacion', $ambienteFacturacion)
            ->where('tipo_facturacion', $tipoFacturacion)
            ->lockForUpdate()
            ->first();

        $nextNumber = max((int) ($correlativo?->ultimo_numero ?? 0), $ultimoNumeroExistente) + 1;

        DB::table('factura_correlativos')
            ->where('id', $correlativo->id)
            ->update([
                'ultimo_numero' => $nextNumber,
                'updated_at' => now(),
            ]);

        return $nextNumber;
    }

    public function existsManualNumberForCafc(int $cafcId, int $numeroFactura, ?int $exceptFacturaId = null): bool
    {
        return Factura::query()
            ->where('cafc_id', $cafcId)
            ->where('numero_factura', $numeroFactura)
            ->when($exceptFacturaId, fn ($query) => $query->whereKeyNot($exceptFacturaId))
            ->exists();
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        $query
            ->when(
                filled($filters['search'] ?? null),
                function (Builder $query) use ($filters) {
                    $search = trim((string) $filters['search']);
                    $invoiceNumber = preg_match('/^FAC-0*(\d+)$/i', $search, $matches)
                        ? (int) $matches[1]
                        : null;
                    $query->where(function (Builder $nested) use ($search, $invoiceNumber) {
                        $nested
                            ->where('numero_factura', 'like', "%{$search}%")
                            ->when($invoiceNumber, fn (Builder $invoice) => $invoice->orWhere('numero_factura', $invoiceNumber))
                            ->orWhere('cuf', 'like', "%{$search}%")
                            ->orWhereHas('cliente', fn (Builder $cliente) => $cliente
                                ->where('nombre', 'like', "%{$search}%")
                                ->orWhere('razon_social', 'like', "%{$search}%")
                                ->orWhere('nit_ci', 'like', "%{$search}%"));
                    });
                },
            )
            ->when(
                filled($filters['fecha_desde'] ?? null),
                fn (Builder $query) => $query->whereDate('fecha_emision', '>=', $filters['fecha_desde']),
            )
            ->when(
                filled($filters['fecha_hasta'] ?? null),
                fn (Builder $query) => $query->whereDate('fecha_emision', '<=', $filters['fecha_hasta']),
            )
            ->when(
                filled($filters['cliente_id'] ?? null),
                fn (Builder $query) => $query->where('cliente_id', $filters['cliente_id']),
            )
            ->when(
                filled($filters['sucursal_id'] ?? null),
                fn (Builder $query) => $query->where('sucursal_id', $filters['sucursal_id']),
            )
            ->when(
                filled($filters['punto_venta_id'] ?? null),
                fn (Builder $query) => $query->where('punto_venta_id', $filters['punto_venta_id']),
            );
    }
}
