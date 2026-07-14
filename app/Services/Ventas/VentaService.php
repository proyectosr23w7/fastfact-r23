<?php

namespace App\Services\Ventas;

use App\Actions\Ventas\GenerarPdfVentaAction;
use App\Actions\Ventas\CalcularCostoSalidaAction;
use App\Actions\Ventas\CalcularUtilidadVentaAction;
use App\Actions\Ventas\ConsumirLotesVentaAction;
use App\Actions\Ventas\DescontarStockArticuloAction;
use App\Actions\Ventas\RegistrarKardexVentaAction;
use App\Actions\Ventas\RevertirVentaAction;
use App\Enums\MetodoSalidaEnum;
use App\Enums\FacturaEstadoEnum;
use App\Enums\RolSistemaEnum;
use App\Enums\TipoDocumentoVentaEnum;
use App\Enums\VentaEstadoEnum;
use App\Helpers\SiatMetodoPagoHelper;
use App\Models\Articulo;
use App\Models\ArticuloPrecio;
use App\Models\Cliente;
use App\Models\Configuracion\Configuracion;
use App\Models\Configuracion\PuntoVenta;
use App\Models\Configuracion\Sucursal;
use App\Models\User;
use App\Models\VentaCabecera;
use App\Models\SinDocumentoIdentidad;
use App\Models\SinMetodoPago;
use App\Repositories\Ventas\VentaCabeceraRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class VentaService
{
    private const IVA_RATE = 0.13;

    private const IT_RATE = 0.03;

    public function __construct(
        private readonly VentaCabeceraRepository $repository,
        private readonly GenerarPdfVentaAction $generarPdfVenta,
        private readonly DescontarStockArticuloAction $descontarStockArticulo,
        private readonly CalcularCostoSalidaAction $calcularCostoSalida,
        private readonly ConsumirLotesVentaAction $consumirLotesVenta,
        private readonly RegistrarKardexVentaAction $registrarKardexVenta,
        private readonly RevertirVentaAction $revertirVenta,
        private readonly CalcularUtilidadVentaAction $calcularUtilidadVenta,
        private readonly \App\Services\Facturacion\EventoSignificativoService $eventoSignificativoService,
    ) {
    }

    public function listar(array $filters = []): LengthAwarePaginator
    {
        return $this->repository->allForIndex($filters);
    }

    public function resumen(): array
    {
        return $this->repository->summary();
    }

    public function crear(array $data): VentaCabecera
    {
        return DB::transaction(function () use ($data): VentaCabecera {
            $configuracion = Configuracion::current();
            $facturacionActiva = (bool) ($configuracion?->facturacionSiatActiva() ?? false);
            $detalle = $this->normalizarDetalle(Arr::pull($data, 'detalle', []));
            $descuentoGlobal = round((float) ($data['descuento_global'] ?? 0), 2);
            $totales = $this->calcularTotales($detalle, $descuentoGlobal);

            $data['numero_venta'] = $this->repository->getNextNumber();
            $data['estado'] = VentaEstadoEnum::BORRADOR->value;
            $data['subtotal'] = $totales['subtotal'];
            $data['descuento'] = $totales['descuento_global'];
            $data['impuesto'] = $totales['impuesto'];
            $data['iva'] = $totales['iva'];
            $data['it'] = $totales['it'];
            $data['total'] = $totales['total'];
            $data['total_costo'] = 0;
            $data['utilidad_bruta'] = 0;
            $data['codigo_metodo_pago'] = $this->resolveDefaultMetodoPagoCode($data['codigo_metodo_pago'] ?? null);
            $data['numero_tarjeta'] = $this->resolveCardNumber($data['codigo_metodo_pago'], $data['numero_tarjeta'] ?? null);
            $data['monto_gift_card'] = $this->resolveGiftCardAmount(
                $data['codigo_metodo_pago'],
                $data['monto_gift_card'] ?? null,
                (float) $data['total'],
            );
            $facturacionObligatoria = (bool) ($configuracion?->ventasFacturacionObligatoria() ?? false);
            $data['requiere_factura'] = $facturacionObligatoria || ($facturacionActiva && (bool) ($data['requiere_factura'] ?? false));

            if ($data['requiere_factura']) {
                $data['tipo_documento_venta'] = TipoDocumentoVentaEnum::FACTURA->value;
            }

            if (! $facturacionActiva && ($data['tipo_documento_venta'] ?? null) === TipoDocumentoVentaEnum::FACTURA->value) {
                $data['tipo_documento_venta'] = TipoDocumentoVentaEnum::NOTA_VENTA->value;
            }

            $venta = $this->repository->create($data);
            $venta->detalle()->createMany($detalle);

            return $this->repository->refresh($venta);
        });
    }

    public function actualizar(VentaCabecera $venta, array $data): VentaCabecera
    {
        if ($venta->estado !== VentaEstadoEnum::BORRADOR) {
            abort(422, 'Solo se pueden editar ventas en borrador.');
        }

        return DB::transaction(function () use ($venta, $data): VentaCabecera {
            $configuracion = Configuracion::current();
            $facturacionActiva = (bool) ($configuracion?->facturacionSiatActiva() ?? false);
            $detalle = $this->normalizarDetalle(Arr::pull($data, 'detalle', []));
            $descuentoGlobal = round((float) ($data['descuento_global'] ?? $venta->descuento ?? 0), 2);
            $totales = $this->calcularTotales($detalle, $descuentoGlobal);

            $data['subtotal'] = $totales['subtotal'];
            $data['descuento'] = $totales['descuento_global'];
            $data['impuesto'] = $totales['impuesto'];
            $data['iva'] = $totales['iva'];
            $data['it'] = $totales['it'];
            $data['total'] = $totales['total'];
            $data['codigo_metodo_pago'] = $this->resolveDefaultMetodoPagoCode($data['codigo_metodo_pago'] ?? $venta->codigo_metodo_pago);
            $data['numero_tarjeta'] = $this->resolveCardNumber($data['codigo_metodo_pago'], $data['numero_tarjeta'] ?? $venta->numero_tarjeta);
            $data['monto_gift_card'] = $this->resolveGiftCardAmount(
                $data['codigo_metodo_pago'],
                $data['monto_gift_card'] ?? $venta->monto_gift_card,
                (float) $data['total'],
            );
            $facturacionObligatoria = (bool) ($configuracion?->ventasFacturacionObligatoria() ?? false);
            $data['requiere_factura'] = $facturacionObligatoria || ($facturacionActiva && (bool) ($data['requiere_factura'] ?? false));

            if ($data['requiere_factura']) {
                $data['tipo_documento_venta'] = TipoDocumentoVentaEnum::FACTURA->value;
            }

            if (! $facturacionActiva && ($data['tipo_documento_venta'] ?? null) === TipoDocumentoVentaEnum::FACTURA->value) {
                $data['tipo_documento_venta'] = TipoDocumentoVentaEnum::NOTA_VENTA->value;
            }

            $venta = $this->repository->update($venta, $data);
            $venta->detalle()->delete();
            $venta->detalle()->createMany($detalle);

            return $this->repository->refresh($venta);
        });
    }

    public function confirmar(VentaCabecera $venta, ?string $observacion = null): VentaCabecera
    {
        return DB::transaction(function () use ($venta, $observacion): VentaCabecera {
            $venta = $this->repository->findForProcess($venta);

            if ($venta->estado !== VentaEstadoEnum::BORRADOR) {
                abort(422, 'Solo se pueden confirmar ventas en borrador.');
            }

            if ($venta->detalle->isEmpty()) {
                abort(422, 'La venta debe tener al menos un detalle para confirmarse.');
            }

            $metodoSalida = $this->metodoSalidaActual();
            $totalCosto = 0;
            $totalUtilidad = 0;

            foreach ($venta->detalle as $detalle) {
                $articulo = Articulo::query()->lockForUpdate()->findOrFail($detalle->articulo_id);

                $costoSalida = ($this->calcularCostoSalida)(
                    $articulo,
                    $venta->sucursal_id,
                    (float) $detalle->cantidad,
                    $metodoSalida,
                );

                ($this->descontarStockArticulo)(
                    $articulo,
                    $venta->sucursal_id,
                    (float) $detalle->cantidad,
                    'restar',
                );

                $consumos = ($this->consumirLotesVenta)($detalle, $costoSalida['consumos']);
                $utilidad = ($this->calcularUtilidadVenta)(
                    (float) $detalle->total,
                    (float) $costoSalida['costo_total'],
                );

                $detalle->update([
                    'costo_unitario' => (float) $costoSalida['costo_unitario'],
                    'costo_total' => (float) $costoSalida['costo_total'],
                    'utilidad_bruta' => (float) $utilidad,
                    'lote_consumido' => collect($consumos)->map(fn (array $consumo) => [
                        'articulo_lote_id' => $consumo['articulo_lote_id'] ?? null,
                        'lote' => $consumo['lote'],
                        'cantidad' => $consumo['cantidad'],
                    ])->values()->all(),
                ]);

                ($this->registrarKardexVenta)(
                    $venta,
                    $detalle->refresh(),
                    $articulo->refresh(),
                    $observacion ?: $venta->observacion,
                );

                $totalCosto += (float) $costoSalida['costo_total'];
                $totalUtilidad += (float) $utilidad;
            }

            $venta = $this->repository->update($venta, [
                'estado' => VentaEstadoEnum::CONFIRMADA->value,
                'observacion' => $observacion ?: $venta->observacion,
                'total_costo' => round($totalCosto, 2),
                'utilidad_bruta' => round($totalUtilidad, 2),
            ]);

            return $this->repository->refresh($venta);
        });
    }

    public function anular(VentaCabecera $venta, ?string $observacion = null): VentaCabecera
    {
        return DB::transaction(function () use ($venta, $observacion): VentaCabecera {
            $venta = $this->repository->findForProcess($venta);

            if ($venta->factura !== null) {
                $estadoFactura = is_string($venta->factura->estado_factura)
                    ? $venta->factura->estado_factura
                    : $venta->factura->estado_factura?->value;

                if (! in_array($estadoFactura, [FacturaEstadoEnum::ANULADA->value, FacturaEstadoEnum::RECHAZADA->value], true)) {
                    abort(
                        422,
                        sprintf(
                            'No se puede anular la venta %s porque tiene la factura %s en estado %s. Primero debes anular la factura.',
                            $venta->numero_venta,
                            (string) ($venta->factura->numero_factura ?? '-'),
                            $estadoFactura ?: 'emitida',
                        ),
                    );
                }
            }

            ($this->revertirVenta)($venta, $observacion);

            $venta = $this->repository->update($venta, [
                'estado' => VentaEstadoEnum::ANULADA->value,
                'observacion' => $observacion ?: $venta->observacion,
            ]);

            return $this->repository->refresh($venta);
        });
    }

    public function downloadPdf(VentaCabecera $venta): Response
    {
        $venta = $this->repository->findForProcess($venta);
        $pdf = ($this->generarPdfVenta)($venta);

        return response($pdf['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$pdf['filename'].'"',
        ]);
    }

    public function meta(?User $user = null): array
    {
        $configuracion = Configuracion::current();
        $preciosPorCantidad = (bool) ($configuracion?->precios_por_cantidad ?? false);
        $multiplesPrecios = (bool) ($configuracion?->multiples_precios ?? false);
        $facturacionActiva = (bool) ($configuracion?->facturacionSiatActiva() ?? false);
        $puedeCambiarContextoOperativo = (bool) ($user?->hasRole(RolSistemaEnum::SUPERADMIN->value) ?? false);
        $articulos = Articulo::query()
            ->with([
                'categoria:id,nombre',
                'marca:id,nombre',
                'precios:id,articulo_id,cantidad_minima,precio,tipo_precio,estado',
            ])
            ->withCount('ventaDetalles')
            ->where('estado', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'descripcion', 'categoria_id', 'marca_id', 'codigo_generico', 'codigo_barras', 'tags', 'alias', 'atributos', 'precio_base', 'costo', 'stock_actual']);

        return [
            'estados' => array_map(
                fn (VentaEstadoEnum $estado) => ['label' => ucfirst($estado->value), 'value' => $estado->value],
                VentaEstadoEnum::cases(),
            ),
            'tipos_documento_venta' => array_map(
                fn (TipoDocumentoVentaEnum $tipo) => ['label' => str_replace('_', ' ', ucfirst($tipo->value)), 'value' => $tipo->value],
                array_values(array_filter(
                    TipoDocumentoVentaEnum::cases(),
                    fn (TipoDocumentoVentaEnum $tipo) => $facturacionActiva || $tipo !== TipoDocumentoVentaEnum::FACTURA,
                )),
            ),
            'metodo_salida' => $this->metodoSalidaActual(),
            'precios_por_cantidad' => $preciosPorCantidad,
            'multiples_precios' => $multiplesPrecios,
            'confirmacion_rapida_ventas' => (bool) ($configuracion?->confirmacion_rapida_ventas ?? false),
            'facturacion_activa' => $facturacionActiva,
            'facturacion_obligatoria_ventas' => (bool) ($configuracion?->ventasFacturacionObligatoria() ?? false),
            'tipo_facturacion' => (int) ($configuracion?->tipo_facturacion ?? 0),
            'usuario_puede_cambiar_contexto_operativo' => $puedeCambiarContextoOperativo,
            'clientes' => Cliente::query()
                ->where('estado', true)
                ->orderBy('nombre')
                ->get(['id', 'codigo', 'nombre', 'razon_social', 'nit_ci', 'tipo_documento_identidad', 'complemento', 'telefono', 'correo', 'estado'])
                ->map(fn (Cliente $cliente) => [
                    'id' => $cliente->id,
                    'codigo' => $cliente->codigo,
                    'nombre' => $cliente->nombre,
                    'razon_social' => $cliente->razon_social,
                    'nit_ci' => $cliente->nit_ci,
                    'tipo_documento_identidad' => $cliente->tipo_documento_identidad,
                    'complemento' => $cliente->complemento,
                    'telefono' => $cliente->telefono,
                    'correo' => $cliente->correo,
                    'estado' => (bool) $cliente->estado,
                ]),
            'sucursales' => Sucursal::query()
                ->where('estado', true)
                ->orderBy('codigo')
                ->get(['id', 'codigo', 'nombre'])
                ->map(fn (Sucursal $sucursal) => [
                    'id' => $sucursal->id,
                    'codigo' => $sucursal->codigo,
                    'nombre' => $sucursal->nombre,
                ]),
            'puntos_venta' => PuntoVenta::query()
                ->where('estado', true)
                ->orderBy('sucursal_id')
                ->orderBy('codigo')
                ->get(['id', 'sucursal_id', 'codigo', 'nombre'])
                ->map(fn (PuntoVenta $puntoVenta) => [
                    'id' => $puntoVenta->id,
                    'sucursal_id' => $puntoVenta->sucursal_id,
                    'codigo' => $puntoVenta->codigo,
                    'nombre' => $puntoVenta->nombre,
                ]),
            'usuarios' => User::query()
                ->where('estado', true)
                ->orderBy('name')
                ->get(['id', 'name', 'email'])
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ]),
            'articulos' => $articulos->map(fn (Articulo $articulo) => [
                'id' => $articulo->id,
                'nombre' => $articulo->nombre,
                'descripcion' => $articulo->descripcion,
                'codigo_generico' => $articulo->codigo_generico,
                'codigo_barras' => $articulo->codigo_barras,
                'codigo_actividad_economica' => $articulo->codigo_actividad_economica,
                'codigo_producto_sin' => $articulo->codigo_producto_sin,
                'codigo_unidad_medida_siat' => $articulo->codigo_unidad_medida_siat,
                'tags' => $articulo->tags ?? [],
                'alias' => $articulo->alias ?? [],
                'atributos' => $articulo->atributos ?? [],
                'categoria' => $articulo->categoria ? [
                    'id' => $articulo->categoria->id,
                    'nombre' => $articulo->categoria->nombre,
                ] : null,
                'marca' => $articulo->marca ? [
                    'id' => $articulo->marca->id,
                    'nombre' => $articulo->marca->nombre,
                ] : null,
                'precio_base' => (float) $articulo->precio_base,
                'costo' => (float) $articulo->costo,
                'stock_actual' => (float) $articulo->stock_actual,
                'ventas_count' => (int) ($articulo->venta_detalles_count ?? 0),
                'stocks' => [],
                'precios' => $articulo->precios
                    ->where('estado', true)
                    ->sortBy('cantidad_minima')
                    ->values()
                    ->map(fn (ArticuloPrecio $precio) => [
                        'cantidad_minima' => (float) $precio->cantidad_minima,
                        'precio' => (float) $precio->precio,
                        'tipo_precio' => $precio->tipo_precio,
                    ]),
            ]),
            'documentos_identidad' => SinDocumentoIdentidad::query()
                ->where('estado', true)
                ->orderBy('descripcion')
                ->get(['id', 'codigo_clasificador', 'descripcion']),
            'metodos_pago_siat' => SinMetodoPago::query()
                ->where('estado', true)
                ->where('habilitado_venta', true)
                ->orderByDesc('es_predeterminado')
                ->orderBy('orden_operativo')
                ->orderBy('descripcion')
                ->get(['id', 'codigo_clasificador', 'descripcion', 'habilitado_venta', 'es_predeterminado', 'orden_operativo'])
                ->map(fn (SinMetodoPago $metodo) => [
                    'id' => $metodo->id,
                    'codigo_clasificador' => $metodo->codigo_clasificador,
                    'descripcion' => $metodo->descripcion,
                    'habilitado_venta' => (bool) $metodo->habilitado_venta,
                    'es_predeterminado' => (bool) $metodo->es_predeterminado,
                    'orden_operativo' => (int) $metodo->orden_operativo,
                    'requires_card_number' => SiatMetodoPagoHelper::requiresCardNumber(
                        (string) $metodo->codigo_clasificador,
                        (string) $metodo->descripcion,
                    ),
                    'requires_gift_card_amount' => SiatMetodoPagoHelper::requiresGiftCardAmount(
                        (string) $metodo->codigo_clasificador,
                        (string) $metodo->descripcion,
                    ),
                ]),
            'eventos_significativos_activos' => $this->eventoSignificativoService->meta($user)['eventos_activos'] ?? [],
            'eventos_significativos_facturables' => $this->eventoSignificativoService
                ->eventosFacturablesPorContexto([], $user)
                ->map(fn ($evento) => [
                    'id' => $evento->id,
                    'codigo_evento' => $evento->codigo_evento,
                    'descripcion' => $evento->descripcion,
                    'tipo_contingencia' => $evento->tipo_contingencia,
                    'estado' => $evento->estado,
                    'sucursal_id' => $evento->sucursal_id,
                    'punto_venta_id' => $evento->punto_venta_id,
                    'fecha_inicio' => optional($evento->fecha_inicio)?->format('Y-m-d H:i:s'),
                    'fecha_fin' => optional($evento->fecha_fin)?->format('Y-m-d H:i:s'),
                    'cafc_id' => $evento->cafc_id,
                    'cafc_codigo' => $evento->cafc?->codigo,
                ])
                ->values(),
        ];
    }

    public function stockSuficiente(VentaCabecera $venta): void
    {
        $metodoSalida = $this->metodoSalidaActual();

        foreach ($venta->detalle as $detalle) {
            $articulo = Articulo::query()->findOrFail($detalle->articulo_id);
            ($this->calcularCostoSalida)(
                $articulo,
                $venta->sucursal_id,
                (float) $detalle->cantidad,
                $metodoSalida,
                true,
            );
        }
    }

    private function normalizarDetalle(array $detalle): array
    {
        return collect($detalle)
            ->groupBy(fn (array $item) => implode('|', [
                $item['articulo_id'],
                (string) $item['precio_unitario'],
            ]))
            ->map(function ($items) {
                $first = $items->first();
                $cantidad = round((float) $items->sum('cantidad'), 2);
                $descuento = round((float) $items->sum('descuento'), 2);
                $subtotal = round($cantidad * (float) $first['precio_unitario'], 2);
                $total = round(max($subtotal - $descuento, 0), 2);

                return [
                    'articulo_id' => (int) $first['articulo_id'],
                    'cantidad' => $cantidad,
                    'precio_unitario' => round((float) $first['precio_unitario'], 2),
                    'descuento' => $descuento,
                    'impuesto' => 0,
                    'subtotal' => $subtotal,
                    'total' => $total,
                    'costo_unitario' => 0,
                    'costo_total' => 0,
                    'utilidad_bruta' => 0,
                    'lote_consumido' => null,
                ];
            })
            ->values()
            ->all();
    }

    private function calcularTotales(array $detalle, float $descuentoGlobal = 0): array
    {
        $subtotal = round((float) collect($detalle)->sum('subtotal'), 2);
        $descuentoItems = round((float) collect($detalle)->sum('descuento'), 2);
        $descuentoGlobal = round(max($descuentoGlobal, 0), 2);
        $totalNeto = round(max($subtotal - $descuentoItems - $descuentoGlobal, 0), 2);
        $iva = round($totalNeto * self::IVA_RATE, 2);
        $it = round($totalNeto * self::IT_RATE, 2);

        return [
            'subtotal' => $subtotal,
            'descuento_items' => $descuentoItems,
            'descuento_global' => $descuentoGlobal,
            // Mantiene el campo legado "impuesto" como alias del IVA.
            'impuesto' => $iva,
            'iva' => $iva,
            'it' => $it,
            'total' => $totalNeto,
        ];
    }

    private function resolveDefaultMetodoPagoCode(string|int|null $codigo): ?string
    {
        $value = trim((string) ($codigo ?? ''));

        if ($value !== '') {
            return $value;
        }

        $metodo = SinMetodoPago::query()
            ->where('estado', true)
            ->where('habilitado_venta', true)
            ->orderByDesc('es_predeterminado')
            ->orderBy('orden_operativo')
            ->orderBy('descripcion')
            ->value('codigo_clasificador');

        if ($metodo) {
            return (string) $metodo;
        }

        $primero = SinMetodoPago::query()
            ->where('estado', true)
            ->where('habilitado_venta', true)
            ->orderBy('orden_operativo')
            ->orderBy('descripcion')
            ->value('codigo_clasificador');

        return $primero ? (string) $primero : null;
    }

    private function resolveCardNumber(string|int|null $codigoMetodoPago, ?string $numeroTarjeta): ?string
    {
        $codigo = trim((string) ($codigoMetodoPago ?? ''));

        if ($codigo === '') {
            return null;
        }

        $metodoPago = SinMetodoPago::query()
            ->where('estado', true)
            ->where('codigo_clasificador', $codigo)
            ->first();

        if (! SiatMetodoPagoHelper::requiresCardNumber($codigo, $metodoPago?->descripcion)) {
            return null;
        }

        return SiatMetodoPagoHelper::normalizeCardNumber($numeroTarjeta);
    }

    private function resolveGiftCardAmount(string|int|null $codigoMetodoPago, mixed $montoGiftCard, float $totalVenta): ?float
    {
        $codigo = trim((string) ($codigoMetodoPago ?? ''));

        if ($codigo === '') {
            return null;
        }

        $metodoPago = SinMetodoPago::query()
            ->where('estado', true)
            ->where('codigo_clasificador', $codigo)
            ->first();

        if (! SiatMetodoPagoHelper::requiresGiftCardAmount($codigo, $metodoPago?->descripcion)) {
            return null;
        }

        $amount = SiatMetodoPagoHelper::normalizeGiftCardAmount($montoGiftCard);

        if ($amount === null) {
            return null;
        }

        return min($amount, round($totalVenta, 2));
    }

    private function metodoSalidaActual(): string
    {
        $value = (string) (Configuracion::current()?->metodo_salida ?: MetodoSalidaEnum::PEPS->value);

        return in_array($value, array_column(MetodoSalidaEnum::cases(), 'value'), true)
            ? $value
            : MetodoSalidaEnum::PEPS->value;
    }
}
