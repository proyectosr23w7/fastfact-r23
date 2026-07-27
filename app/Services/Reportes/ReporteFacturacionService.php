<?php

namespace App\Services\Reportes;

use App\Enums\FacturaEstadoEnum;
use App\Models\Factura;
use App\Models\FacturaDetalle;
use App\Models\SinMetodoPago;
use App\Models\User;
use App\Support\OperationalContextScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ReporteFacturacionService
{
    public function generar(array $filters, ?User $user = null): array
    {
        $filters = $this->normalizarFiltros($filters, $user);
        $baseQuery = $this->baseFacturasQuery($filters, $user);
        $facturasValidasQuery = $this->soloFacturasOperativas(clone $baseQuery);

        return [
            'resumen' => $this->resumen(clone $baseQuery, clone $facturasValidasQuery),
            'por_estado' => $this->porEstado(clone $baseQuery),
            'por_metodo_pago' => $this->porMetodoPago(clone $baseQuery),
            'por_usuario' => $this->porUsuario(clone $baseQuery),
            'por_producto' => $this->porProducto($filters, $user),
            'facturas_recientes' => $this->facturasRecientes(clone $baseQuery),
            'meta' => $this->meta($filters, $user),
        ];
    }

    private function normalizarFiltros(array $filters, ?User $user): array
    {
        $filters = OperationalContextScope::mergeFilters($filters, $user);

        $filters['fecha_desde'] = $this->parseDate($filters['fecha_desde'] ?? null)
            ?? now()->startOfMonth();
        $filters['fecha_hasta'] = $this->parseDate($filters['fecha_hasta'] ?? null)
            ?? now();

        if ($filters['fecha_hasta']->lt($filters['fecha_desde'])) {
            $filters['fecha_hasta'] = $filters['fecha_desde']->copy()->endOfDay();
        }

        return $filters;
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function baseFacturasQuery(array $filters, ?User $user): Builder
    {
        $query = Factura::query()
            ->with(['cliente:id,nombre,razon_social,nit_ci', 'sucursal:id,codigo,nombre', 'puntoVenta:id,sucursal_id,codigo,nombre', 'user:id,name,email'])
            ->whereBetween('facturas.fecha_emision', [
                $filters['fecha_desde']->copy()->startOfDay(),
                $filters['fecha_hasta']->copy()->endOfDay(),
            ]);

        return $query
            ->when(
                ! OperationalContextScope::isGlobal($user) && $user?->punto_venta_id,
                fn ($query) => $query->where('facturas.punto_venta_id', $user->punto_venta_id),
            )
            ->when(
                ! OperationalContextScope::isGlobal($user) && ! $user?->punto_venta_id && $user?->sucursal_id,
                fn ($query) => $query->where('facturas.sucursal_id', $user->sucursal_id),
            )
            ->when(filled($filters['sucursal_id'] ?? null), fn ($query) => $query->where('facturas.sucursal_id', $filters['sucursal_id']))
            ->when(filled($filters['punto_venta_id'] ?? null), fn ($query) => $query->where('facturas.punto_venta_id', $filters['punto_venta_id']))
            ->when(filled($filters['user_id'] ?? null), fn ($query) => $query->where('facturas.user_id', $filters['user_id']))
            ->when(filled($filters['estado_factura'] ?? null), fn ($query) => $query->where('facturas.estado_factura', $filters['estado_factura']))
            ->when(filled($filters['codigo_metodo_pago'] ?? null), fn ($query) => $query->where('facturas.codigo_metodo_pago', $filters['codigo_metodo_pago']));
    }

    private function soloFacturasOperativas(Builder $query): Builder
    {
        return $query->whereNotIn('estado_factura', [
            FacturaEstadoEnum::ANULADA->value,
            FacturaEstadoEnum::RECHAZADA->value,
        ]);
    }

    private function resumen(Builder $baseQuery, Builder $validasQuery): array
    {
        return [
            'facturas_total' => (int) (clone $baseQuery)->count(),
            'facturas_operativas' => (int) (clone $validasQuery)->count(),
            'monto_total' => round((float) (clone $validasQuery)->sum('monto_total'), 2),
            'monto_sujeto_iva' => round((float) (clone $validasQuery)->sum('monto_sujeto_iva'), 2),
            'descuentos' => round((float) (clone $validasQuery)->sum('descuento_global'), 2),
            'anuladas' => (int) (clone $baseQuery)->where('estado_factura', FacturaEstadoEnum::ANULADA->value)->count(),
            'observadas' => (int) (clone $baseQuery)->where('estado_factura', FacturaEstadoEnum::OBSERVADA->value)->count(),
            'pendientes' => (int) (clone $baseQuery)->whereIn('estado_factura', [
                FacturaEstadoEnum::PENDIENTE->value,
                FacturaEstadoEnum::PENDIENTE_ENVIO->value,
            ])->count(),
        ];
    }

    private function porEstado(Builder $query): array
    {
        return $query
            ->select('estado_factura', DB::raw('COUNT(*) as cantidad'), DB::raw('COALESCE(SUM(monto_total), 0) as total'))
            ->groupBy('estado_factura')
            ->orderBy('estado_factura')
            ->get()
            ->map(fn ($row) => [
                'estado' => $this->estadoValue($row->estado_factura),
                'cantidad' => (int) $row->cantidad,
                'total' => round((float) $row->total, 2),
            ])
            ->values()
            ->all();
    }

    private function porMetodoPago(Builder $query): array
    {
        $metodos = SinMetodoPago::query()
            ->pluck('descripcion', 'codigo_clasificador')
            ->mapWithKeys(fn ($descripcion, $codigo) => [(string) $codigo => $descripcion]);

        return $this->soloFacturasOperativas($query)
            ->select('codigo_metodo_pago', DB::raw('COUNT(*) as cantidad'), DB::raw('COALESCE(SUM(monto_total), 0) as total'))
            ->groupBy('codigo_metodo_pago')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'codigo' => (string) $row->codigo_metodo_pago,
                'descripcion' => (string) ($metodos[(string) $row->codigo_metodo_pago] ?? 'Metodo '.$row->codigo_metodo_pago),
                'cantidad' => (int) $row->cantidad,
                'total' => round((float) $row->total, 2),
            ])
            ->values()
            ->all();
    }

    private function porUsuario(Builder $query): array
    {
        return $this->soloFacturasOperativas($query)
            ->leftJoin('users', 'users.id', '=', 'facturas.user_id')
            ->select('facturas.user_id', DB::raw("COALESCE(users.name, 'Sin usuario') as usuario"), DB::raw('COUNT(*) as cantidad'), DB::raw('COALESCE(SUM(facturas.monto_total), 0) as total'))
            ->groupBy('facturas.user_id', 'users.name')
            ->orderByDesc('total')
            ->limit(20)
            ->get()
            ->map(fn ($row) => [
                'user_id' => $row->user_id ? (int) $row->user_id : null,
                'usuario' => (string) $row->usuario,
                'cantidad' => (int) $row->cantidad,
                'total' => round((float) $row->total, 2),
            ])
            ->values()
            ->all();
    }

    private function porProducto(array $filters, ?User $user): array
    {
        $query = FacturaDetalle::query()
            ->join('facturas', 'facturas.id', '=', 'factura_detalles.factura_id')
            ->whereBetween('facturas.fecha_emision', [
                $filters['fecha_desde']->copy()->startOfDay(),
                $filters['fecha_hasta']->copy()->endOfDay(),
            ])
            ->whereNotIn('facturas.estado_factura', [
                FacturaEstadoEnum::ANULADA->value,
                FacturaEstadoEnum::RECHAZADA->value,
            ]);

        return $query
            ->when(
                ! OperationalContextScope::isGlobal($user) && $user?->punto_venta_id,
                fn ($query) => $query->where('facturas.punto_venta_id', $user->punto_venta_id),
            )
            ->when(
                ! OperationalContextScope::isGlobal($user) && ! $user?->punto_venta_id && $user?->sucursal_id,
                fn ($query) => $query->where('facturas.sucursal_id', $user->sucursal_id),
            )
            ->when(filled($filters['sucursal_id'] ?? null), fn ($query) => $query->where('facturas.sucursal_id', $filters['sucursal_id']))
            ->when(filled($filters['punto_venta_id'] ?? null), fn ($query) => $query->where('facturas.punto_venta_id', $filters['punto_venta_id']))
            ->when(filled($filters['user_id'] ?? null), fn ($query) => $query->where('facturas.user_id', $filters['user_id']))
            ->when(filled($filters['estado_factura'] ?? null), fn ($query) => $query->where('facturas.estado_factura', $filters['estado_factura']))
            ->when(filled($filters['codigo_metodo_pago'] ?? null), fn ($query) => $query->where('facturas.codigo_metodo_pago', $filters['codigo_metodo_pago']))
            ->select(
                'factura_detalles.codigo_producto',
                'factura_detalles.descripcion',
                DB::raw('COALESCE(SUM(factura_detalles.cantidad), 0) as cantidad'),
                DB::raw('COALESCE(SUM(factura_detalles.subtotal), 0) as total'),
                DB::raw('COALESCE(AVG(factura_detalles.precio_unitario), 0) as precio_promedio'),
            )
            ->groupBy('factura_detalles.codigo_producto', 'factura_detalles.descripcion')
            ->orderByDesc('total')
            ->limit(30)
            ->get()
            ->map(fn ($row) => [
                'codigo_producto' => (string) $row->codigo_producto,
                'descripcion' => (string) $row->descripcion,
                'cantidad' => round((float) $row->cantidad, 5),
                'total' => round((float) $row->total, 2),
                'precio_promedio' => round((float) $row->precio_promedio, 2),
            ])
            ->values()
            ->all();
    }

    private function facturasRecientes(Builder $query): array
    {
        return $query
            ->latest('fecha_emision')
            ->limit(20)
            ->get()
            ->map(fn (Factura $factura) => [
                'id' => (int) $factura->id,
                'numero_factura' => (int) $factura->numero_factura,
                'fecha_emision' => optional($factura->fecha_emision)?->format('Y-m-d H:i:s'),
                'cliente' => $factura->cliente?->razon_social ?: $factura->cliente?->nombre,
                'usuario' => $factura->user?->name,
                'sucursal' => $factura->sucursal?->nombre,
                'punto_venta' => $factura->puntoVenta?->nombre,
                'estado_factura' => $this->estadoValue($factura->estado_factura),
                'codigo_metodo_pago' => (string) $factura->codigo_metodo_pago,
                'monto_total' => round((float) $factura->monto_total, 2),
            ])
            ->values()
            ->all();
    }

    private function meta(array $filters, ?User $user): array
    {
        return [
            'filtros' => [
                'fecha_desde' => $filters['fecha_desde']->format('Y-m-d'),
                'fecha_hasta' => $filters['fecha_hasta']->format('Y-m-d'),
                'sucursal_id' => $filters['sucursal_id'] ?? null,
                'punto_venta_id' => $filters['punto_venta_id'] ?? null,
                'user_id' => $filters['user_id'] ?? null,
                'estado_factura' => $filters['estado_factura'] ?? null,
                'codigo_metodo_pago' => $filters['codigo_metodo_pago'] ?? null,
            ],
            'sucursales' => OperationalContextScope::sucursalesQuery($user)->get(['id', 'codigo', 'nombre']),
            'puntos_venta' => OperationalContextScope::puntosVentaQuery($user)->get(['id', 'sucursal_id', 'codigo', 'nombre']),
            'usuarios' => User::query()
                ->where('estado', true)
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
            'estados_factura' => array_map(
                fn (FacturaEstadoEnum $estado) => ['value' => $estado->value, 'label' => ucfirst(str_replace('_', ' ', $estado->value))],
                FacturaEstadoEnum::cases(),
            ),
            'metodos_pago' => SinMetodoPago::query()
                ->where('estado', true)
                ->orderBy('codigo_clasificador')
                ->get(['codigo_clasificador', 'descripcion']),
        ];
    }

    private function estadoValue(mixed $estado): string
    {
        if ($estado instanceof FacturaEstadoEnum) {
            return $estado->value;
        }

        return (string) $estado;
    }
}
