<?php

namespace App\Services\Facturacion;

use App\Actions\Facturacion\ConsultarEstadoFacturaAction;
use App\Actions\Facturacion\EnviarFacturaSiatAction;
use App\Actions\Facturacion\FirmarXmlFacturaAction;
use App\Actions\Facturacion\GenerarCufFacturaAction;
use App\Actions\Facturacion\GenerarDatosFacturaDesdeVentaAction;
use App\Actions\Facturacion\GenerarPdfFacturaAction;
use App\Actions\Facturacion\GenerarXmlFacturaAction;
use App\Actions\Facturacion\ObtenerCufdVigenteAction;
use App\Actions\Facturacion\ObtenerCuisVigenteAction;
use App\Actions\Facturacion\RegistrarAnulacionFacturaAction;
use App\Actions\Facturacion\RegistrarReversionAnulacionFacturaAction;
use App\Actions\Facturacion\RegistrarRespuestaSiatAction;
use App\Actions\Facturacion\ValidarVentaFacturableAction;
use App\Enums\FacturaEstadoEnum;
use App\Helpers\SiatMetodoPagoHelper;
use App\Models\Configuracion\Configuracion;
use App\Models\Configuracion\Empresa;
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
use App\Models\VentaCabecera;
use App\Repositories\Facturacion\FacturaRepository;
use App\Support\OperationalContextScope;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FacturaService
{
    public function __construct(
        private readonly FacturaRepository $repository,
        private readonly ValidarVentaFacturableAction $validarVentaFacturable,
        private readonly ObtenerCuisVigenteAction $obtenerCuisVigente,
        private readonly ObtenerCufdVigenteAction $obtenerCufdVigente,
        private readonly GenerarDatosFacturaDesdeVentaAction $generarDatosFacturaDesdeVenta,
        private readonly GenerarCufFacturaAction $generarCufFactura,
        private readonly GenerarXmlFacturaAction $generarXmlFactura,
        private readonly FirmarXmlFacturaAction $firmarXmlFactura,
        private readonly GenerarPdfFacturaAction $generarPdfFactura,
        private readonly EnviarFacturaSiatAction $enviarFacturaSiat,
        private readonly RegistrarRespuestaSiatAction $registrarRespuestaSiat,
        private readonly ConsultarEstadoFacturaAction $consultarEstadoFactura,
        private readonly RegistrarAnulacionFacturaAction $registrarAnulacionFactura,
        private readonly RegistrarReversionAnulacionFacturaAction $registrarReversionAnulacionFactura,
        private readonly SiatEndpointResolver $endpointResolver,
        private readonly CufdService $cufdService,
        private readonly EventoSignificativoService $eventoSignificativoService,
        private readonly FacturaCorreoService $facturaCorreoService,
    ) {
    }

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

    public function emitir(array $data, User $user): Factura
    {
        return DB::transaction(function () use ($data, $user): Factura {
            $venta = VentaCabecera::query()
                ->with(['cliente', 'sucursal', 'puntoVenta', 'user', 'detalle.articulo', 'factura'])
                ->lockForUpdate()
                ->findOrFail($data['venta_id']);

            if ($venta->factura) {
                abort(422, 'La venta ya tiene una factura registrada.');
            }

            OperationalContextScope::authorize(
                $user,
                (int) $venta->sucursal_id,
                (int) $venta->punto_venta_id,
            );

            $configuracion = Configuracion::current();
            if (! $configuracion) {
                abort(422, 'No existe una configuracion general registrada para facturar.');
            }
            $empresa = Empresa::query()->firstOrFail();

            ($this->validarVentaFacturable)($venta, $configuracion);

            $cuis = ($this->obtenerCuisVigente)($venta->sucursal_id, $venta->punto_venta_id, $configuracion->ambiente_facturacion ?: 'piloto');
            $ambiente = $configuracion->ambiente_facturacion ?: 'piloto';
            $eventoActivo = $this->eventoSignificativoService->eventoActivoPorContexto($venta->sucursal_id, $venta->punto_venta_id, $ambiente);

            if (! $cuis) {
                abort(422, 'Debe existir un CUIS vigente para emitir la factura.');
            }

            $numeroFactura = $this->repository->getNextInvoiceNumber();
            $fechaEmision = now(config('app.timezone'));
            $payloadOverrides = [
                'codigo_metodo_pago' => $data['codigo_metodo_pago'] ?? null,
                'numero_tarjeta' => $data['numero_tarjeta'] ?? null,
                'monto_gift_card' => $data['monto_gift_card'] ?? null,
                'codigo_documento_identidad' => $data['codigo_documento_identidad'] ?? null,
                'numero_factura' => $numeroFactura,
                'fecha_emision' => $fechaEmision,
                'numero_factura_manual' => $data['numero_factura_manual'] ?? null,
                'fecha_emision_manual' => $data['fecha_emision_manual'] ?? null,
            ];

            if ($eventoActivo) {
                if ((string) $eventoActivo->tipo_contingencia === 'manual') {
                    return $this->emitirContingenciaManual(
                        $venta,
                        $configuracion,
                        $empresa,
                        $user,
                        $cuis,
                        $eventoActivo,
                        $payloadOverrides,
                    );
                }

                return $this->emitirFueraDeLinea(
                    $venta,
                    $configuracion,
                    $empresa,
                    $user,
                    $cuis,
                    $eventoActivo,
                    $payloadOverrides,
                );
            }

            $eventoManualPendiente = $this->eventoSignificativoService->eventoManualPendientePorContexto(
                $venta->sucursal_id,
                $venta->punto_venta_id,
                $ambiente,
            );

            if ($eventoManualPendiente) {
                return $this->emitirContingenciaManual(
                    $venta,
                    $configuracion,
                    $empresa,
                    $user,
                    $cuis,
                    $eventoManualPendiente,
                    $payloadOverrides,
                );
            }

            $cufd = ($this->obtenerCufdVigente)($venta->sucursal_id, $venta->punto_venta_id, $ambiente);

            if (! $cufd) {
                abort(422, 'Debe existir un CUFD vigente para emitir la factura.');
            }

            $payload = $this->buildPayloadForCufd(
                $venta,
                $configuracion,
                $empresa,
                $payloadOverrides,
                $cufd,
                1,
                null,
            );

            $metodoPagoFactura = SinMetodoPago::query()
                ->where('estado', true)
                ->where('codigo_clasificador', (string) $payload['cabecera']['codigoMetodoPago'])
                ->first();

            if (
                SiatMetodoPagoHelper::requiresCardNumber(
                    (string) $payload['cabecera']['codigoMetodoPago'],
                    $metodoPagoFactura?->descripcion,
                )
                && ! filled($payload['cabecera']['numeroTarjeta'] ?? null)
            ) {
                abort(422, 'La venta usa metodo de pago tarjeta. Debes completar los 4 primeros y 4 ultimos digitos de la tarjeta antes de emitir la factura.');
            }

            if (
                SiatMetodoPagoHelper::requiresGiftCardAmount(
                    (string) $payload['cabecera']['codigoMetodoPago'],
                    $metodoPagoFactura?->descripcion,
                )
                && ! filled($payload['cabecera']['montoGiftCard'] ?? null)
            ) {
                abort(422, 'La venta usa metodo de pago gift card. Debes completar el monto gift card antes de emitir la factura.');
            }

            $xml = ($this->generarXmlFactura)($payload, $venta->id);
            $signedXml = ($this->firmarXmlFactura)($xml['xml'], $venta->id, (int) $configuracion->tipo_facturacion);

            $factura = $this->repository->create([
                'venta_id' => $venta->id,
                'cliente_id' => $venta->cliente_id,
                'sucursal_id' => $venta->sucursal_id,
                'punto_venta_id' => $venta->punto_venta_id,
                'user_id' => $user->id,
                'cuis_id' => $cuis->id,
                'cufd_id' => $cufd->id,
                'numero_factura' => $numeroFactura,
                'cuf' => $payload['cabecera']['cuf'],
                'codigo_metodo_pago' => $data['codigo_metodo_pago'] ?? null,
                'numero_tarjeta' => $payload['cabecera']['numeroTarjeta'],
                'monto_gift_card' => $payload['cabecera']['montoGiftCard'],
                'descuento_global' => (float) ($venta->descuento ?? 0),
                'codigo_documento_identidad' => $data['codigo_documento_identidad'] ?? null,
                'tipo_facturacion' => (int) $configuracion->tipo_facturacion,
                'ambiente_facturacion' => $configuracion->ambiente_facturacion,
                'codigo_emision' => 1,
                'xml_generado_path' => null,
                'xml_firmado_path' => null,
                'hash_xml' => $xml['hash'],
                'xml_fiscal' => $signedXml['xml'],
                'fecha_emision' => $fechaEmision,
                'monto_total' => $venta->total,
                'monto_sujeto_iva' => (float) ($payload['cabecera']['montoTotalSujetoIva'] ?? $venta->total),
                'estado_factura' => FacturaEstadoEnum::PENDIENTE->value,
                'estado_sincronizacion' => 'no_aplica',
                'observacion' => $data['observacion'] ?? $venta->observacion,
            ]);

            $response = ($this->enviarFacturaSiat)([
                'factura_id' => $factura->id,
                'venta_id' => $venta->id,
                'xml_content' => $signedXml['xml'],
                'cuis' => $cuis->codigo,
                'cufd' => $cufd->codigo,
                'payload' => $payload,
            ]);

            if ($this->shouldRetryWithFreshCufd($response)) {
                $cufd = $this->cufdService->registrar([
                    'sucursal_id' => $venta->sucursal_id,
                    'punto_venta_id' => $venta->punto_venta_id,
                    'forzar_nuevo' => true,
                ], $user->id);

                $payload = $this->buildPayloadForCufd(
                    $venta,
                    $configuracion,
                    $empresa,
                    $payloadOverrides,
                    $cufd,
                    1,
                    null,
                );
                $xml = ($this->generarXmlFactura)($payload, $venta->id);
                $signedXml = ($this->firmarXmlFactura)($xml['xml'], $venta->id, (int) $configuracion->tipo_facturacion);

                $factura = $this->repository->update($factura, [
                    'cufd_id' => $cufd->id,
                    'cuf' => $payload['cabecera']['cuf'],
                    'hash_xml' => $xml['hash'],
                    'xml_fiscal' => $signedXml['xml'],
                ]);

                $response = ($this->enviarFacturaSiat)([
                    'factura_id' => $factura->id,
                    'venta_id' => $venta->id,
                    'xml_content' => $signedXml['xml'],
                    'cuis' => $cuis->codigo,
                    'cufd' => $cufd->codigo,
                    'payload' => $payload,
                ]);
            }

            $factura = ($this->registrarRespuestaSiat)($factura, $response);

            $venta->update([
                'cuf' => $factura->cuf,
                'cufd' => $cufd->codigo,
                'codigo_recepcion' => $factura->codigo_recepcion,
                'numero_factura' => $factura->numero_factura,
                'codigo_excepcion' => $factura->codigo_excepcion,
                'estado_facturacion' => is_string($factura->estado_factura) ? $factura->estado_factura : $factura->estado_factura?->value,
            ]);

            DB::afterCommit(fn () => $this->facturaCorreoService->enviarFacturaEmitida($this->repository->refresh($factura)));

            return $this->repository->refresh($factura);
        });
    }

    public function reintentar(Factura $factura, array $data, User $user): Factura
    {
        return DB::transaction(function () use ($factura, $data, $user): Factura {
            $factura = $this->repository->findForProcess($factura);

            if (! in_array((string) $factura->estado_factura->value, [FacturaEstadoEnum::RECHAZADA->value, FacturaEstadoEnum::OBSERVADA->value], true)) {
                abort(422, 'Solo se pueden reintentar facturas rechazadas u observadas.');
            }

            if ((int) $factura->codigo_emision === 2) {
                abort(422, 'Las facturas emitidas en contingencia se recuperan por paquetes SIAT. No corresponde reintentar su envio individual.');
            }

            $venta = $factura->venta?->load(['cliente', 'sucursal', 'puntoVenta', 'user', 'detalle.articulo', 'factura']);

            if (! $venta) {
                abort(422, 'La factura no tiene una venta asociada para reintentar la emision.');
            }

            $configuracion = Configuracion::current();
            if (! $configuracion) {
                abort(422, 'No existe una configuracion general registrada para facturar.');
            }
            $empresa = Empresa::query()->firstOrFail();

            ($this->validarVentaFacturable)($venta, $configuracion);

            $ambiente = $configuracion->ambiente_facturacion ?: 'piloto';
            $cuis = ($this->obtenerCuisVigente)($venta->sucursal_id, $venta->punto_venta_id, $ambiente);
            $cufd = ($this->obtenerCufdVigente)($venta->sucursal_id, $venta->punto_venta_id, $ambiente);

            if (! $cuis || ! $cufd) {
                abort(422, 'Debe existir un CUIS y un CUFD vigentes para reintentar la factura.');
            }

            $fechaEmision = now(config('app.timezone'));
            $payloadOverrides = [
                'codigo_metodo_pago' => $data['codigo_metodo_pago'] ?? $factura->codigo_metodo_pago,
                'numero_tarjeta' => $data['numero_tarjeta'] ?? $factura->numero_tarjeta,
                'monto_gift_card' => array_key_exists('monto_gift_card', $data)
                    ? $data['monto_gift_card']
                    : $factura->monto_gift_card,
                'codigo_documento_identidad' => $data['codigo_documento_identidad'] ?? $factura->codigo_documento_identidad,
                'numero_factura' => (int) ($factura->numero_factura ?: $this->repository->getNextInvoiceNumber()),
                'fecha_emision' => $fechaEmision,
            ];

            $payload = $this->buildPayloadForCufd(
                $venta,
                $configuracion,
                $empresa,
                $payloadOverrides,
                $cufd,
                1,
                null,
            );

            $metodoPagoFactura = SinMetodoPago::query()
                ->where('estado', true)
                ->where('codigo_clasificador', (string) $payload['cabecera']['codigoMetodoPago'])
                ->first();

            if (
                SiatMetodoPagoHelper::requiresCardNumber(
                    (string) $payload['cabecera']['codigoMetodoPago'],
                    $metodoPagoFactura?->descripcion,
                )
                && ! filled($payload['cabecera']['numeroTarjeta'] ?? null)
            ) {
                abort(422, 'La factura usa metodo de pago tarjeta. Debes completar los 4 primeros y 4 ultimos digitos de la tarjeta antes de reintentar.');
            }

            if (
                SiatMetodoPagoHelper::requiresGiftCardAmount(
                    (string) $payload['cabecera']['codigoMetodoPago'],
                    $metodoPagoFactura?->descripcion,
                )
                && ! filled($payload['cabecera']['montoGiftCard'] ?? null)
            ) {
                abort(422, 'La factura usa metodo de pago gift card. Debes completar el monto gift card antes de reintentar.');
            }

            $xml = ($this->generarXmlFactura)($payload, $venta->id);
            $signedXml = ($this->firmarXmlFactura)($xml['xml'], $venta->id, (int) $configuracion->tipo_facturacion);

            $factura = $this->repository->update($factura, [
                'cliente_id' => $venta->cliente_id,
                'sucursal_id' => $venta->sucursal_id,
                'punto_venta_id' => $venta->punto_venta_id,
                'user_id' => $user->id,
                'cuis_id' => $cuis->id,
                'cufd_id' => $cufd->id,
                'numero_factura' => $payloadOverrides['numero_factura'],
                'cuf' => $payload['cabecera']['cuf'],
                'codigo_metodo_pago' => $payloadOverrides['codigo_metodo_pago'] ?? null,
                'numero_tarjeta' => $payload['cabecera']['numeroTarjeta'],
                'monto_gift_card' => $payload['cabecera']['montoGiftCard'],
                'codigo_documento_identidad' => $payloadOverrides['codigo_documento_identidad'] ?? null,
                'tipo_facturacion' => (int) $configuracion->tipo_facturacion,
                'ambiente_facturacion' => $configuracion->ambiente_facturacion,
                'hash_xml' => $xml['hash'],
                'xml_fiscal' => $signedXml['xml'],
                'fecha_emision' => $fechaEmision,
                'monto_total' => $venta->total,
                'monto_sujeto_iva' => (float) ($payload['cabecera']['montoTotalSujetoIva'] ?? $venta->total),
                'estado_factura' => FacturaEstadoEnum::PENDIENTE->value,
                'codigo_recepcion' => null,
                'codigo_estado' => null,
                'descripcion_estado' => null,
                'codigo_excepcion' => null,
                'datos_respuesta_siat' => null,
                'observacion' => $data['observacion'] ?? $factura->observacion ?? $venta->observacion,
            ]);

            $response = ($this->enviarFacturaSiat)([
                'factura_id' => $factura->id,
                'venta_id' => $venta->id,
                'xml_content' => $signedXml['xml'],
                'cuis' => $cuis->codigo,
                'cufd' => $cufd->codigo,
                'payload' => $payload,
            ]);

            if ($this->shouldRetryWithFreshCufd($response)) {
                $cufd = $this->cufdService->registrar([
                    'sucursal_id' => $venta->sucursal_id,
                    'punto_venta_id' => $venta->punto_venta_id,
                    'forzar_nuevo' => true,
                ], $user->id);

                $payload = $this->buildPayloadForCufd(
                    $venta,
                    $configuracion,
                    $empresa,
                    $payloadOverrides,
                    $cufd,
                    1,
                    null,
                );
                $xml = ($this->generarXmlFactura)($payload, $venta->id);
                $signedXml = ($this->firmarXmlFactura)($xml['xml'], $venta->id, (int) $configuracion->tipo_facturacion);

                $factura = $this->repository->update($factura, [
                    'cufd_id' => $cufd->id,
                    'cuf' => $payload['cabecera']['cuf'],
                    'hash_xml' => $xml['hash'],
                    'xml_fiscal' => $signedXml['xml'],
                    'fecha_emision' => $fechaEmision,
                ]);

                $response = ($this->enviarFacturaSiat)([
                    'factura_id' => $factura->id,
                    'venta_id' => $venta->id,
                    'xml_content' => $signedXml['xml'],
                    'cuis' => $cuis->codigo,
                    'cufd' => $cufd->codigo,
                    'payload' => $payload,
                ]);
            }

            $factura = ($this->registrarRespuestaSiat)($factura, $response);

            $venta->update([
                'cuf' => $factura->cuf,
                'cufd' => $cufd->codigo,
                'codigo_recepcion' => $factura->codigo_recepcion,
                'numero_factura' => $factura->numero_factura,
                'codigo_excepcion' => $factura->codigo_excepcion,
                'estado_facturacion' => is_string($factura->estado_factura) ? $factura->estado_factura : $factura->estado_factura?->value,
            ]);

            DB::afterCommit(fn () => $this->facturaCorreoService->enviarFacturaEmitida($this->repository->refresh($factura)));

            return $this->repository->refresh($factura);
        });
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
                $factura->venta?->update(['estado_facturacion' => FacturaEstadoEnum::ANULADA->value]);
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

            $factura->venta?->update(['estado_facturacion' => FacturaEstadoEnum::EMITIDA->value]);

            DB::afterCommit(fn () => $this->facturaCorreoService->enviarAdvertenciaReversionAnulacion($this->repository->refresh($factura)));

            return $this->repository->refresh($factura);
        });
    }

    public function meta(?User $user = null, array $filters = []): array
    {
        $configuracion = Configuracion::current();
        $ambiente = (string) ($configuracion?->ambiente_facturacion ?: 'piloto');
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
        $cuis = $sucursalId && $puntoVentaId
            ? Cuis::query()
                ->where('sucursal_id', $sucursalId)
                ->where('punto_venta_id', $puntoVentaId)
                ->where('ambiente_facturacion', $ambiente)
                ->where('estado', true)
                ->where('fecha_vigencia', '>=', now())
                ->latest('fecha_vigencia')
                ->first()
            : null;
        $cufd = $sucursalId && $puntoVentaId
            ? Cufd::query()
                ->where('sucursal_id', $sucursalId)
                ->where('punto_venta_id', $puntoVentaId)
                ->where('ambiente_facturacion', $ambiente)
                ->where('estado', true)
                ->where('fecha_vigencia', '>=', now())
                ->latest('fecha_vigencia')
                ->first()
            : null;
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
                'reintentar' => (bool) $user?->hasPermission('facturacion.access'),
                'anular' => (bool) $user?->hasPermission('facturacion.access'),
                'descargar' => (bool) $user?->hasPermission('facturacion.access'),
            ],
            'estados_factura' => array_map(
                fn (FacturaEstadoEnum $estado) => ['label' => ucfirst($estado->value), 'value' => $estado->value],
                FacturaEstadoEnum::cases(),
            ),
            'clientes' => \App\Models\Cliente::query()->where('estado', true)->orderBy('nombre')->get(['id', 'nombre', 'razon_social', 'nit_ci']),
            'sucursales' => OperationalContextScope::sucursalesQuery($user)->get(['id', 'codigo', 'nombre']),
            'puntos_venta' => OperationalContextScope::puntosVentaQuery($user)->get(['id', 'sucursal_id', 'codigo', 'nombre']),
            'usuarios' => User::query()->where('estado', true)->orderBy('name')->get(['id', 'name', 'email']),
            'ventas_facturables' => $this->repository->ventasFacturables($user)->map(fn (VentaCabecera $venta) => [
                'id' => $venta->id,
                'numero_venta' => $venta->numero_venta,
                'fecha_venta' => optional($venta->fecha_venta)?->format('Y-m-d'),
                'cliente' => $venta->cliente?->razon_social ?: $venta->cliente?->nombre,
                'nit_ci' => $venta->cliente?->nit_ci,
                'sucursal_id' => $venta->sucursal_id,
                'punto_venta_id' => $venta->punto_venta_id,
                'sucursal' => $venta->sucursal?->nombre,
                'punto_venta' => $venta->puntoVenta?->nombre,
                'total' => (float) $venta->total,
            ])->values(),
            'motivos_anulacion' => SinMotivoAnulacion::query()->where('estado', true)->orderBy('descripcion')->get(['id', 'codigo_clasificador', 'descripcion']),
            'documentos_identidad' => SinDocumentoIdentidad::query()->where('estado', true)->orderBy('descripcion')->get(['id', 'codigo_clasificador', 'descripcion']),
            'productos_servicios' => SinProductoServicio::query()
                ->where('estado', true)
                ->orderBy('descripcion')
                ->limit(300)
                ->get(['id', 'codigo_actividad', 'codigo_producto', 'descripcion']),
            'unidades_medida' => SinUnidadMedida::query()
                ->where('estado', true)
                ->where('habilitado_uso', true)
                ->orderBy('descripcion')
                ->get(['id', 'codigo_clasificador', 'descripcion']),            'metodos_pago' => SinMetodoPago::query()
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
                ]),
            'siat' => $this->endpointResolver->profile(),
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

    public function downloadXml(Factura $factura): Response
    {
        $factura = $this->repository->refresh($factura);
        [$xml, $filename] = $this->regenerateFacturaXml($factura);

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    public function downloadPdf(Factura $factura): Response
    {
        $factura = $this->repository->refresh($factura);
        $pdf = ($this->generarPdfFactura)($factura);

        return response($pdf['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$pdf['filename'].'"',
        ]);
    }

    private function buildPayloadForCufd(
        VentaCabecera $venta,
        Configuracion $configuracion,
        Empresa $empresa,
        array $payloadOverrides,
        \App\Models\Cufd $cufd,
        int $codigoEmision = 1,
        ?int $eventoSignificativoId = null,
    ): array {
        $payload = ($this->generarDatosFacturaDesdeVenta)(
            $venta,
            $configuracion,
            $empresa,
            array_merge($payloadOverrides, [
                'cufd' => $cufd->codigo,
                'codigo_emision' => $codigoEmision,
                'evento_significativo_id' => $eventoSignificativoId,
                'cafc' => $payloadOverrides['cafc'] ?? null,
                'codigo_excepcion' => $payloadOverrides['codigo_excepcion'] ?? null,
            ]),
        );

        $payload['cabecera']['cuf'] = ($this->generarCufFactura)(
            $payload['cabecera'],
            $cufd,
            (int) $configuracion->tipo_facturacion,
            $codigoEmision,
        );

        return $payload;
    }

    private function shouldRetryWithFreshCufd(array $response): bool
    {
        if (($response['success'] ?? false) === true) {
            return false;
        }

        $hayCufInvalido = $this->responseContainsAny($response, [
            'CODIGO UNICO DE FACTURA (CUF) ENVIADO EN EL XML ES INVALIDO',
            'CODIGO UNICO DE FACTURACION DIARIA (CUFD) ENVIADO EN EL XML ES INVALIDO',
        ]);

        return $hayCufInvalido;
    }

    private function responseContainsAny(array $response, array $needles): bool
    {
        $haystack = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (! is_string($haystack) || $haystack === '') {
            return false;
        }

        foreach ($needles as $needle) {
            if (str_contains(mb_strtoupper($haystack), mb_strtoupper($needle))) {
                return true;
            }
        }

        return false;
    }

    private function regenerateFacturaXml(Factura $factura): array
    {
        if (filled($factura->xml_fiscal)) {
            return [(string) $factura->xml_fiscal, "factura-venta-{$factura->venta_id}.xml"];
        }

        $factura->loadMissing(['venta.cliente', 'venta.sucursal', 'venta.puntoVenta', 'venta.user', 'venta.detalle.articulo.unidadMedida', 'cufd', 'cafc', 'eventoSignificativo.cafc']);

        $configuracion = Configuracion::query()->firstOrFail();
        $empresa = Empresa::query()->firstOrFail();

        $payload = ($this->generarDatosFacturaDesdeVenta)(
            $factura->venta,
            $configuracion,
            $empresa,
            [
                'codigo_metodo_pago' => $factura->codigo_metodo_pago,
                'numero_tarjeta' => $factura->numero_tarjeta ?: $factura->venta?->numero_tarjeta,
                'monto_gift_card' => $factura->monto_gift_card ?: $factura->venta?->monto_gift_card,
                'codigo_documento_identidad' => $factura->codigo_documento_identidad,
                'numero_factura' => $factura->numero_factura,
                'cufd' => $factura->cufd?->codigo ?: $factura->venta?->cufd,
                'fecha_emision' => $factura->fecha_emision,
                'codigo_emision' => (int) ($factura->codigo_emision ?: 1),
                'evento_significativo_id' => $factura->evento_significativo_id,
                'cafc' => $factura->cafc?->codigo ?: $factura->eventoSignificativo?->cafc?->codigo,
            ],
        );
        $payload['cabecera']['cuf'] = $factura->cuf;

        $xml = ($this->generarXmlFactura)($payload, $factura->venta_id);

        if ((int) $factura->tipo_facturacion === \App\Enums\TipoFacturacionEnum::ELECTRONICA->value) {
            $signed = ($this->firmarXmlFactura)($xml['xml'], $factura->venta_id, (int) $factura->tipo_facturacion);

            return [$signed['xml'], $signed['filename']];
        }

        return [$xml['xml'], $xml['filename']];
    }

    private function emitirFueraDeLinea(
        VentaCabecera $venta,
        Configuracion $configuracion,
        Empresa $empresa,
        User $user,
        \App\Models\Cuis $cuis,
        \App\Models\EventoSignificativo $eventoActivo,
        array $payloadOverrides,
    ): Factura {
        $cufdEvento = $eventoActivo->cufdEvento;

        if (! $cufdEvento) {
            abort(422, 'El evento significativo activo no tiene un CUFD de contingencia asociado.');
        }

        $payload = $this->buildPayloadForCufd(
            $venta,
            $configuracion,
            $empresa,
            $payloadOverrides,
            $cufdEvento,
            2,
            $eventoActivo->id,
        );

        $metodoPagoFactura = SinMetodoPago::query()
            ->where('estado', true)
            ->where('codigo_clasificador', (string) $payload['cabecera']['codigoMetodoPago'])
            ->first();

        if (
            SiatMetodoPagoHelper::requiresCardNumber(
                (string) $payload['cabecera']['codigoMetodoPago'],
                $metodoPagoFactura?->descripcion,
            )
            && ! filled($payload['cabecera']['numeroTarjeta'] ?? null)
        ) {
            abort(422, 'La venta usa metodo de pago tarjeta. Debes completar los 4 primeros y 4 ultimos digitos de la tarjeta antes de emitir la factura.');
        }

        if (
            SiatMetodoPagoHelper::requiresGiftCardAmount(
                (string) $payload['cabecera']['codigoMetodoPago'],
                $metodoPagoFactura?->descripcion,
            )
            && ! filled($payload['cabecera']['montoGiftCard'] ?? null)
        ) {
            abort(422, 'La venta usa metodo de pago gift card. Debes completar el monto gift card antes de emitir la factura.');
        }

        $xml = ($this->generarXmlFactura)($payload, $venta->id);
        $signedXml = ($this->firmarXmlFactura)($xml['xml'], $venta->id, (int) $configuracion->tipo_facturacion);

        $factura = $this->repository->create([
            'venta_id' => $venta->id,
            'cliente_id' => $venta->cliente_id,
            'sucursal_id' => $venta->sucursal_id,
            'punto_venta_id' => $venta->punto_venta_id,
            'user_id' => $user->id,
            'cuis_id' => $cuis->id,
            'cufd_id' => $cufdEvento->id,
            'evento_significativo_id' => $eventoActivo->id,
            'numero_factura' => $payloadOverrides['numero_factura'],
            'cuf' => $payload['cabecera']['cuf'],
            'codigo_metodo_pago' => $payloadOverrides['codigo_metodo_pago'] ?? null,
            'numero_tarjeta' => $payload['cabecera']['numeroTarjeta'],
            'monto_gift_card' => $payload['cabecera']['montoGiftCard'],
            'descuento_global' => (float) ($venta->descuento ?? 0),
            'codigo_documento_identidad' => $payloadOverrides['codigo_documento_identidad'] ?? null,
            'tipo_facturacion' => (int) $configuracion->tipo_facturacion,
            'ambiente_facturacion' => $configuracion->ambiente_facturacion,
            'codigo_emision' => 2,
            'xml_generado_path' => null,
            'xml_firmado_path' => null,
            'hash_xml' => $xml['hash'],
            'xml_fiscal' => $signedXml['xml'],
            'fecha_emision' => $payloadOverrides['fecha_emision'],
            'monto_total' => $venta->total,
            'monto_sujeto_iva' => (float) ($payload['cabecera']['montoTotalSujetoIva'] ?? $venta->total),
            'codigo_estado' => 'CONTINGENCIA_LOCAL',
            'descripcion_estado' => 'Factura emitida fuera de linea por evento significativo activo. Pendiente de registro y envio por paquetes al SIAT.',
            'estado_factura' => FacturaEstadoEnum::PENDIENTE_ENVIO->value,
            'estado_sincronizacion' => 'pendiente_paquete',
            'observacion' => $venta->observacion,
        ]);

        $venta->update([
            'cuf' => $factura->cuf,
            'cufd' => $cufdEvento->codigo,
            'codigo_recepcion' => null,
            'numero_factura' => $factura->numero_factura,
            'estado_facturacion' => FacturaEstadoEnum::PENDIENTE_ENVIO->value,
        ]);

        return $this->repository->refresh($factura);
    }

    private function emitirContingenciaManual(
        VentaCabecera $venta,
        Configuracion $configuracion,
        Empresa $empresa,
        User $user,
        \App\Models\Cuis $cuis,
        \App\Models\EventoSignificativo $eventoActivo,
        array $payloadOverrides,
    ): Factura {
        $cufdEvento = $eventoActivo->cufdEvento;
        $cafc = $eventoActivo->cafc;

        if (! $cufdEvento) {
            abort(422, 'El evento significativo manual no tiene un CUFD del evento asociado.');
        }

        if (! $cafc) {
            abort(422, 'El evento significativo manual no tiene un CAFC asociado.');
        }

        if (! $cafc->estado) {
            abort(422, 'El CAFC asociado al evento manual se encuentra inactivo. Debes revisar su administracion antes de transcribir facturas.');
        }

        $numeroFacturaManual = (int) ($payloadOverrides['numero_factura_manual'] ?? 0);
        if ($numeroFacturaManual <= 0) {
            abort(422, 'Debes registrar el numero manual de la factura de contingencia antes de transcribirla.');
        }

        if ($cafc->numero_inicial !== null && $numeroFacturaManual < (int) $cafc->numero_inicial) {
            abort(422, 'El numero manual de factura es menor al rango autorizado del CAFC.');
        }

        if ($cafc->numero_final !== null && $numeroFacturaManual > (int) $cafc->numero_final) {
            abort(422, 'El numero manual de factura supera el rango autorizado del CAFC.');
        }

        $fechaEmisionManualRaw = $payloadOverrides['fecha_emision_manual'] ?? null;
        if (! filled($fechaEmisionManualRaw)) {
            abort(422, 'Debes registrar la fecha y hora manual de emision de la factura de contingencia.');
        }

        $fechaEmisionManual = $fechaEmisionManualRaw instanceof CarbonInterface
            ? $fechaEmisionManualRaw->copy()->timezone(config('app.timezone'))
            : \Carbon\Carbon::parse((string) $fechaEmisionManualRaw, config('app.timezone'))->timezone(config('app.timezone'));

        $fechaInicioEvento = $eventoActivo->fecha_inicio?->copy()->timezone(config('app.timezone'));
        $fechaFinEvento = $eventoActivo->fecha_fin?->copy()->timezone(config('app.timezone'));
        $fechaMaximaPermitida = $fechaFinEvento ?: now(config('app.timezone'));

        if (! $fechaInicioEvento || $fechaEmisionManual->lt($fechaInicioEvento) || $fechaEmisionManual->gt($fechaMaximaPermitida)) {
            $limiteFin = $fechaMaximaPermitida->format('Y-m-d H:i:s');
            abort(422, "La fecha/hora manual de emision debe estar dentro del rango del evento significativo ({$fechaInicioEvento?->format('Y-m-d H:i:s')} - {$limiteFin}).");
        }

        if ($this->repository->existsManualNumberForCafc($cafc->id, $numeroFacturaManual)) {
            abort(422, 'El numero manual ya fue utilizado con este CAFC. Debes registrar un numero de factura de contingencia distinto.');
        }

        $payloadOverrides['numero_factura'] = $numeroFacturaManual;
        $payloadOverrides['fecha_emision'] = $fechaEmisionManual;
        $payloadOverrides['cafc'] = $cafc->codigo;

        $payload = $this->buildPayloadForCufd(
            $venta,
            $configuracion,
            $empresa,
            $payloadOverrides,
            $cufdEvento,
            2,
            $eventoActivo->id,
        );

        $metodoPagoFactura = SinMetodoPago::query()
            ->where('estado', true)
            ->where('codigo_clasificador', (string) $payload['cabecera']['codigoMetodoPago'])
            ->first();

        if (
            SiatMetodoPagoHelper::requiresCardNumber(
                (string) $payload['cabecera']['codigoMetodoPago'],
                $metodoPagoFactura?->descripcion,
            )
            && ! filled($payload['cabecera']['numeroTarjeta'] ?? null)
        ) {
            abort(422, 'La factura manual usa metodo de pago tarjeta. Debes completar los 4 primeros y 4 ultimos digitos antes de transcribirla.');
        }

        if (
            SiatMetodoPagoHelper::requiresGiftCardAmount(
                (string) $payload['cabecera']['codigoMetodoPago'],
                $metodoPagoFactura?->descripcion,
            )
            && ! filled($payload['cabecera']['montoGiftCard'] ?? null)
        ) {
            abort(422, 'La factura manual usa metodo de pago gift card. Debes completar el monto gift card antes de transcribirla.');
        }

        $xml = ($this->generarXmlFactura)($payload, $venta->id);
        $signedXml = ($this->firmarXmlFactura)($xml['xml'], $venta->id, (int) $configuracion->tipo_facturacion);

        $factura = $this->repository->create([
            'venta_id' => $venta->id,
            'cliente_id' => $venta->cliente_id,
            'sucursal_id' => $venta->sucursal_id,
            'punto_venta_id' => $venta->punto_venta_id,
            'user_id' => $user->id,
            'cuis_id' => $cuis->id,
            'cufd_id' => $cufdEvento->id,
            'evento_significativo_id' => $eventoActivo->id,
            'evento_significativo_paquete_id' => null,
            'cafc_id' => $cafc->id,
            'numero_factura' => $numeroFacturaManual,
            'cuf' => $payload['cabecera']['cuf'],
            'codigo_metodo_pago' => $payloadOverrides['codigo_metodo_pago'] ?? null,
            'numero_tarjeta' => $payload['cabecera']['numeroTarjeta'],
            'monto_gift_card' => $payload['cabecera']['montoGiftCard'],
            'descuento_global' => (float) ($venta->descuento ?? 0),
            'codigo_documento_identidad' => $payloadOverrides['codigo_documento_identidad'] ?? null,
            'tipo_facturacion' => (int) $configuracion->tipo_facturacion,
            'ambiente_facturacion' => $configuracion->ambiente_facturacion,
            'codigo_emision' => 2,
            'xml_generado_path' => null,
            'xml_firmado_path' => null,
            'hash_xml' => $xml['hash'],
            'xml_fiscal' => $signedXml['xml'],
            'fecha_emision' => $fechaEmisionManual,
            'monto_total' => $venta->total,
            'monto_sujeto_iva' => (float) ($payload['cabecera']['montoTotalSujetoIva'] ?? $venta->total),
            'codigo_estado' => 'CONTINGENCIA_MANUAL',
            'descripcion_estado' => 'Factura manual de contingencia transcrita localmente con CAFC. Pendiente de envio por paquetes al SIAT.',
            'estado_factura' => FacturaEstadoEnum::PENDIENTE_ENVIO->value,
            'estado_sincronizacion' => 'pendiente_paquete',
            'observacion' => $payloadOverrides['observacion'] ?? $venta->observacion,
        ]);

        $venta->update([
            'cuf' => $factura->cuf,
            'cufd' => $cufdEvento->codigo,
            'codigo_recepcion' => null,
            'numero_factura' => $factura->numero_factura,
            'estado_facturacion' => FacturaEstadoEnum::PENDIENTE_ENVIO->value,
        ]);

        return $this->repository->refresh($factura);
    }
}
