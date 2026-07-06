<?php

namespace App\Http\Controllers;

use App\Models\Configuracion\Configuracion;
use App\Models\Cufd;
use App\Models\Factura;
use App\Models\SiatSincronizacion;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $hoy = now()->startOfDay();
        $inicioSemana = $hoy->copy()->subDays(6);
        $configuracion = Configuracion::current();
        $facturacionActiva = (bool) ($configuracion?->facturacionSiatActiva() ?? false);

        $facturas = Factura::query();
        $facturasNoAnuladas = (clone $facturas)->where('estado_factura', '!=', 'anulada');
        $totalHoy = (float) (clone $facturasNoAnuladas)
            ->whereDate('fecha_emision', $hoy)
            ->sum('monto_total');
        $totalAyer = (float) (clone $facturasNoAnuladas)
            ->whereDate('fecha_emision', $hoy->copy()->subDay())
            ->sum('monto_total');
        $facturasHoy = (clone $facturasNoAnuladas)
            ->whereDate('fecha_emision', $hoy)
            ->count();
        $facturasAyer = (clone $facturasNoAnuladas)
            ->whereDate('fecha_emision', $hoy->copy()->subDay())
            ->count();

        $facturasPorFecha = (clone $facturasNoAnuladas)
            ->whereBetween('fecha_emision', [$inicioSemana->toDateString(), $hoy->toDateString()])
            ->selectRaw('DATE(fecha_emision) as fecha, SUM(monto_total) as total')
            ->groupBy('fecha')
            ->pluck('total', 'fecha');

        $facturasRecientes = (clone $facturas)
            ->with('cliente:id,nombre,razon_social,nit_ci')
            ->latest('fecha_emision')
            ->latest('created_at')
            ->limit(5)
            ->get()
            ->map(fn (Factura $factura) => [
                'id' => $factura->id,
                'numero' => $factura->numero_factura ?: $factura->id,
                'fecha' => $factura->fecha_emision?->toIso8601String() ?: $factura->created_at?->toIso8601String(),
                'cliente' => $factura->cliente?->razon_social ?: $factura->cliente?->nombre ?: 'Consumidor final',
                'total' => (float) $factura->monto_total,
                'estado' => is_string($factura->estado_factura) ? $factura->estado_factura : $factura->estado_factura?->value,
            ]);

        return Inertia::render('Dashboard', [
            'dashboard' => [
                'kpis' => [
                    'facturacion_hoy' => $totalHoy,
                    'variacion_facturacion' => $this->variacion($totalHoy, $totalAyer),
                    'facturas_hoy' => $facturasHoy,
                    'variacion_facturas' => $facturasHoy - $facturasAyer,
                ],
                'facturacion_semana' => collect(range(0, 6))->map(function (int $offset) use ($inicioSemana, $facturasPorFecha) {
                    $fecha = $inicioSemana->copy()->addDays($offset);

                    return [
                        'fecha' => $fecha->toDateString(),
                        'total' => (float) ($facturasPorFecha[$fecha->toDateString()] ?? 0),
                    ];
                }),
                'facturas_recientes' => $facturasRecientes,
                'siat' => $facturacionActiva ? $this->estadoSiat() : null,
            ],
        ]);
    }

    private function variacion(float $actual, float $anterior): ?float
    {
        if ($anterior <= 0) {
            return $actual > 0 ? null : 0.0;
        }

        return round((($actual - $anterior) / $anterior) * 100, 1);
    }

    private function estadoSiat(): array
    {
        $configuracion = Configuracion::current();
        $habilitado = (bool) ($configuracion?->facturacionSiatActiva() ?? false);
        $cufd = Cufd::query()
            ->latest('fecha_vigencia')
            ->first();
        $conectado = $habilitado
            && (bool) $cufd?->estado
            && $cufd?->fecha_vigencia instanceof Carbon
            && $cufd->fecha_vigencia->isFuture();
        $ultimaSincronizacion = SiatSincronizacion::query()
            ->latest('fecha_sincronizacion')
            ->first();

        return [
            'habilitado' => $habilitado,
            'conectado' => $conectado,
            'estado' => $conectado ? 'Conectado' : ($habilitado ? 'Requiere atención' : 'No habilitado'),
            'ambiente' => $configuracion?->ambiente_facturacion ?: null,
            'ultima_sincronizacion' => $ultimaSincronizacion?->fecha_sincronizacion?->toIso8601String(),
            'vigencia_cufd' => $cufd?->fecha_vigencia?->toIso8601String(),
        ];
    }
}
