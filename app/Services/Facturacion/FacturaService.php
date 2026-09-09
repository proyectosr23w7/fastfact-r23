<?php

namespace App\Services\Facturacion;

use App\Actions\Facturacion\ConsultarEstadoFacturaAction;
use App\Actions\Facturacion\GenerarPdfFacturaAction;
use App\Actions\Facturacion\RegistrarAnulacionFacturaAction;
use App\Actions\Facturacion\RegistrarReversionAnulacionFacturaAction;
use App\Enums\FacturaEstadoEnum;
use App\Helpers\SiatMetodoPagoHelper;
use App\Models\Articulo;
use App\Models\Configuracion\Configuracion;
use App\Models\Configuracion\PuntoVenta;
use App\Models\Configuracion\Sucursal;
use App\Models\Cufd;
use App\Models\Cuis;
use App\Models\Factura;
use App\Models\SiatSincronizacion;
use App\Models\SinDocumentoIdentidad;
use App\Models\SinMetodoPago;
use App\Models\SinMotivoAnulacion;
use App\Models\SinProductoServicio;
use App\Models\SinUnidadMedida;
use App\Models\User;
use App\Repositories\Facturacion\FacturaRepository;
use App\Support\OperationalContextScope;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FacturaService
{
    public function __construct(
        private readonly FacturaRepository $repository,
        private readonly GenerarPdfFacturaAction $generarPdfFactura,
        private readonly ConsultarEstadoFacturaAction $consultarEstadoFactura,
        private readonly RegistrarAnulacionFacturaAction $registrarAnulacionFactura,
        private readonly RegistrarReversionAnulacionFacturaAction $registrarReversionAnulacionFactura,
        private readonly SiatEndpointResolver $endpointResolver,
        private readonly EventoSignificativoService $eventoSignificativoService,
        private readonly FacturaCorreoService $facturaCorreoService,
    ) {}

    public function listar(array $filters = [], ?User $user = null): Collection
    {
        return $this->repository->allForIndex($filters, $user);
    }

    public function autorizarAcceso(Factura $factura, ?User $user): void
    {
        OperationalContextScope::authorize(
            $user,
            (int) $factura->sucursal_id,
            (int) $factura->punto_venta_id,
        );
    }

    public function consultar(Factura $factura): Factura
    {
        return DB::transaction(function () use ($factura): Factura {
            $factura = $this->repository->findForProcess($factura);
            $response = ($this->consultarEstadoFactura)($factura);

            return $this->repository->update($factura, [
                'codigo_estado' => $response['code'] ?? $factura->codigo_estado,
                'descripcion_estado' => $response['message'] ?? $factura->descripcion_estado,
                'datos_respuesta_siat' => $response,
            ]);
        });
    }

    public function anular(Factura $factura, array $data, User $user): Factura
    {
        return DB::transaction(function () use ($factura, $data, $user): Factura {
            $factura = $this->repository->findForProcess($factura);

            if (! in_array((string) $factura->estado_factura->value, [FacturaEstadoEnum::EMITIDA->value, FacturaEstadoEnum::OBSERVADA->value], true)) {
                abort(422, 'Solo se pueden anular facturas emitidas u observadas.');
            }

            if (filled($factura->anulacion_revertida_at)) {
                abort(422, 'Esta factura ya tuvo una reversion de anulacion y no puede volver a anularse.');
            }

            $anulacion = ($this->registrarAnulacionFactura)($factura, $data, $user->id);

            if ($factura->fresh()?->estado_factura === FacturaEstadoEnum::ANULADA) {
                DB::afterCommit(fn () => $this->facturaCorreoService->enviarAdvertenciaAnulacion(
                    $this->repository->refresh($factura),
                    $anulacion,
                ));
            }

            return $this->repository->refresh($factura);
        });
    }

    public function revertirAnulacion(Factura $factura, User $user): Factura
    {
        return DB::transaction(function () use ($factura, $user): Factura {
            $factura = $this->repository->findForProcess($factura);

            if ((string) $factura->estado_factura->value !== FacturaEstadoEnum::ANULADA->value) {
                abort(422, 'Solo se puede revertir la anulacion de facturas anuladas.');
            }

            if (filled($factura->anulacion_revertida_at)) {
                abort(422, 'La anulacion de esta factura ya fue revertida previamente.');
            }

            $factura = ($this->registrarReversionAnulacionFactura)($factura, $user->id);

            if ((string) $factura->estado_factura->value !== FacturaEstadoEnum::EMITIDA->value) {
                abort(422, $factura->anulacion_reversion_descripcion ?: 'SIAT no acepto la reversion de anulacion.');
            }

            DB::afterCommit(fn () => $this->facturaCorreoService->enviarAdvertenciaReversionAnulacion($this->repository->refresh($factura)));

            return $this->repository->refresh($factura);
        });
    }

    public function meta(?User $user = null, array $filters = []): array
    {
        $configuracion = Configuracion::current();
        $ambiente = (string) ($configuracion?->ambiente_facturacion ?: 'piloto');
        $metaContext = (string) ($filters['meta_context'] ?? 'listado');
        $includeBillingMeta = $metaContext === 'emision';
        $scopedFilters = OperationalContextScope::mergeFilters($filters, $user);
        $sucursalId = (int) ($scopedFilters['sucursal_id'] ?? 0);
        $puntoVentaId = (int) ($scopedFilters['punto_venta_id'] ?? 0);

        if (! $sucursalId || ! $puntoVentaId) {
            $puntoVenta = PuntoVenta::query()
                ->where('estado', true)
                ->when(
                    ! OperationalContextScope::isGlobal($user) && $user?->punto_venta_id,
                    fn ($query) => $query->whereKey($user->punto_venta_id),
                )
                ->when(
                    ! OperationalContextScope::isGlobal($user) && ! $user?->punto_venta_id && $user?->sucursal_id,
                    fn ($query) => $query->where('sucursal_id', $user->sucursal_id),
                )
                ->when($sucursalId, fn ($query) => $query->where('sucursal_id', $sucursalId))
                ->orderBy('sucursal_id')
                ->orderBy('codigo')
                ->first();
            $sucursalId = (int) ($puntoVenta?->sucursal_id ?? $sucursalId);
            $puntoVentaId = (int) ($puntoVenta?->id ?? $puntoVentaId);
        }

        $sucursalContexto = $sucursalId ? Sucursal::query()->find($sucursalId) : null;
        $puntoVentaContexto = $puntoVentaId ? PuntoVenta::query()->find($puntoVentaId) : null;
        $cuis = $this->codigoVigente(Cuis::query(), $sucursalId, $puntoVentaId, $ambiente);
        $cufd = $this->codigoVigente(Cufd::query(), $sucursalId, $puntoVentaId, $ambiente);
        $eventoActivo = $sucursalId && $puntoVentaId
            ? $this->eventoSignificativoService->eventoActivoPorContexto($sucursalId, $puntoVentaId, $ambiente)
            : null;
        $ultimaSincronizacion = null;

        if (Schema::hasTable('siat_sincronizaciones') && $sucursalContexto && $puntoVentaContexto) {
            $ultimaSincronizacion = SiatSincronizacion::query()
                ->where('codigo_sucursal', $sucursalContexto->codigo)
                ->where('codigo_punto_venta', $puntoVentaContexto->codigo)
                ->latest('fecha_sincronizacion')
                ->first();
        }

        $puedeOperar = (bool) ($configuracion?->facturacionSiatActiva() && $cuis && ($cufd || $eventoActivo));

        return [
            'kpis' => $this->repository->operationalSummary($filters, $user),
            'salud_siat' => [
                'operativo' => $puedeOperar,
                'mensaje' => $eventoActivo
                    ? 'Facturacion operando en modo contingencia.'
                    : ($puedeOperar ? 'Servicios habilitados para emitir facturas.' : 'Faltan datos vigentes para operar con SIAT.'),
                'ambiente' => $ambiente,
                'contexto' => [
                    'sucursal_id' => $sucursalContexto?->id,
                    'sucursal' => $sucursalContexto?->nombre,
                    'punto_venta_id' => $puntoVentaContexto?->id,
                    'punto_venta' => $puntoVentaContexto?->nombre,
                ],
                'cuis' => $cuis ? [
                    'codigo' => $cuis->codigo,
                    'fecha_vigencia' => optional($cuis->fecha_vigencia)?->format('Y-m-d H:i:s'),
                ] : null,
                'cufd' => $cufd ? [
                    'codigo' => $cufd->codigo,
                    'fecha_vigencia' => optional($cufd->fecha_vigencia)?->format('Y-m-d H:i:s'),
                ] : null,
                'ultima_sincronizacion' => $ultimaSincronizacion ? [
                    'fecha' => optional($ultimaSincronizacion->fecha_sincronizacion)?->format('Y-m-d H:i:s'),
                    'estado' => $ultimaSincronizacion->estado,
                    'catalogo' => $ultimaSincronizacion->tipo_catalogo,
                ] : null,
                'contingencia' => $eventoActivo ? [
                    'id' => $eventoActivo->id,
                    'codigo_evento' => $eventoActivo->codigo_evento,
                    'descripcion' => $eventoActivo->descripcion,
                    'tipo' => $eventoActivo->tipo_contingencia,
                    'fecha_inicio' => optional($eventoActivo->fecha_inicio)?->format('Y-m-d H:i:s'),
                ] : null,
            ],
            'capacidades' => [
                'emitir' => (bool) ($user?->hasPermission('facturacion.access') && $configuracion?->facturacionSiatActiva()),
                'consultar' => (bool) $user?->hasPermission('facturacion.access'),
                'reintentar' => false,
                'anular' => (bool) $user?->hasPermission('facturacion.facturas.anular'),
                'revertir_anulacion' => (bool) $user?->hasPermission('facturacion.facturas.revertir_anulacion'),
                'descargar' => (bool) $user?->hasPermission('facturacion.access'),
            ],
            'estados_factura' => array_map(
                fn (FacturaEstadoEnum $estado) => ['label' => ucfirst($estado->value), 'value' => $estado->value],
                FacturaEstadoEnum::cases(),
            ),
            'clientes' => $includeBillingMeta ? \App\Models\Cliente::query()
                ->where('estado', true)
                ->orderBy('nombre')
                ->get([
                    'id',
                    'codigo',
                    'nombre',
                    'razon_social',
                    'nit_ci',
                    'tipo_documento_identidad',
                    'complemento',
                    'telefono',
                    'correo',
                    'direccion',
                    'estado',
                ]) : [],
            'articulos' => $includeBillingMeta ? Articulo::query()
                ->with([
                    'unidadMedida:id,nombre,abreviatura',
                    'precios' => fn ($query) => $query->where('estado', true)->orderBy('cantidad_minima'),
                ])
                ->withCount('facturaDetalles as facturas_count')
                ->where('estado', true)
                ->orderByDesc('facturas_count')
                ->orderBy('nombre')
                ->limit(500)
                ->get()
                ->map(fn (Articulo $articulo) => [
                    'id' => $articulo->id,
                    'codigo_generico' => $articulo->codigo_generico,
                    'codigo_barras' => $articulo->codigo_barras,
                    'nombre' => $articulo->nombre,
                    'descripcion' => $articulo->descripcion,
                    'codigo_actividad_economica' => $articulo->codigo_actividad_economica,
                    'codigo_producto_sin' => $articulo->codigo_producto_sin,
                    'codigo_unidad_medida_siat' => $articulo->codigo_unidad_medida_siat,
                    'precio_base' => (float) $articulo->precio_base,
                    'stock_actual' => (float) $articulo->stock_actual,
                    'facturas_count' => (int) ($articulo->facturas_count ?? 0),
                    'ventas_count' => (int) ($articulo->facturas_count ?? 0),
                    'unidad_medida' => $articulo->unidadMedida ? [
                        'id' => $articulo->unidadMedida->id,
                        'nombre' => $articulo->unidadMedida->nombre,
                        'abreviatura' => $articulo->unidadMedida->abreviatura,
                    ] : null,
                    'precios' => $articulo->precios->map(fn ($precio) => [
                        'id' => $precio->id,
                        'tipo_precio' => $precio->tipo_precio,
                        'cantidad_minima' => (float) $precio->cantidad_minima,
                        'precio' => (float) $precio->precio,
                        'estado' => (bool) $precio->estado,
                    ])->values(),
                    'stocks' => [],
                ])
                ->values() : [],
            'sucursales' => OperationalContextScope::sucursalesQuery($user)->get(['id', 'codigo', 'nombre']),
            'puntos_venta' => OperationalContextScope::puntosVentaQuery($user)->get(['id', 'sucursal_id', 'codigo', 'nombre']),
            'usuarios' => User::query()->where('estado', true)->orderBy('name')->get(['id', 'name', 'email']),
            'motivos_anulacion' => SinMotivoAnulacion::query()->where('estado', true)->orderBy('descripcion')->get(['id', 'codigo_clasificador', 'descripcion']),
            'documentos_identidad' => SinDocumentoIdentidad::query()->where('estado', true)->orderBy('descripcion')->get(['id', 'codigo_clasificador', 'descripcion']),
            'productos_servicios' => $includeBillingMeta ? SinProductoServicio::query()
                ->where('estado', true)
                ->orderBy('descripcion')
                ->limit(300)
                ->get(['id', 'codigo_actividad', 'codigo_producto', 'descripcion']) : [],
            'unidades_medida' => $includeBillingMeta ? SinUnidadMedida::query()
                ->where('estado', true)
                ->where('habilitado_uso', true)
                ->orderBy('descripcion')
                ->get(['id', 'codigo_clasificador', 'descripcion']) : [],
            'metodos_pago' => $this->metodosPago(),
            'metodos_pago_siat' => $this->metodosPago(),
            'facturacion_activa' => (bool) $configuracion?->facturacionSiatActiva(),
            'facturacion_obligatoria_ventas' => true,
            'confirmacion_rapida_ventas' => false,
            'tipos_documento_venta' => [
                ['label' => 'Factura', 'value' => 'factura'],
            ],
            'siat' => $this->endpointResolver->profile(),
            'eventos_significativos_facturables' => $includeBillingMeta ? $this->eventoSignificativoService
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
                ->values() : [],
        ];
    }

    public function downloadXml(Factura $factura): Response
    {
        $factura = $this->repository->refresh($factura);

        if (blank($factura->xml_fiscal)) {
            abort(422, 'La factura no tiene XML fiscal almacenado.');
        }

        return response((string) $factura->xml_fiscal, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="factura-'.$factura->numero_factura.'.xml"',
        ]);
    }

    public function downloadPdf(Factura $factura, ?string $formato = null): Response
    {
        $factura = $this->repository->refresh($factura);
        $pdf = ($this->generarPdfFactura)($factura, $formato);

        return response($pdf['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$pdf['filename'].'"',
        ]);
    }

    private function codigoVigente($query, int $sucursalId, int $puntoVentaId, string $ambiente): mixed
    {
        if (! $sucursalId || ! $puntoVentaId) {
            return null;
        }

        return $query
            ->where('sucursal_id', $sucursalId)
            ->where('punto_venta_id', $puntoVentaId)
            ->where('ambiente_facturacion', $ambiente)
            ->where('estado', true)
            ->where('fecha_vigencia', '>=', now())
            ->latest('fecha_vigencia')
            ->first();
    }

    private function metodosPago(): Collection
    {
        return SinMetodoPago::query()
            ->where('estado', true)
            ->where('habilitado_venta', true)
            ->orderByDesc('es_predeterminado')
            ->orderBy('orden_operativo')
            ->orderBy('descripcion')
            ->get(['id', 'codigo_clasificador', 'descripcion', 'es_predeterminado', 'orden_operativo'])
            ->map(fn (SinMetodoPago $metodo) => [
                'id' => $metodo->id,
                'codigo_clasificador' => $metodo->codigo_clasificador,
                'descripcion' => $metodo->descripcion,
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
            ]);
    }
}
