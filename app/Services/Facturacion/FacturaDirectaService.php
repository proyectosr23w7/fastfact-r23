<?php

namespace App\Services\Facturacion;

use App\Actions\Facturacion\EnviarFacturaSiatAction;
use App\Actions\Facturacion\FirmarXmlFacturaAction;
use App\Actions\Facturacion\GenerarCufFacturaAction;
use App\Actions\Facturacion\GenerarDatosFacturaDirectaAction;
use App\Actions\Facturacion\GenerarXmlFacturaAction;
use App\Actions\Facturacion\ObtenerCuisVigenteAction;
use App\Actions\Facturacion\RegistrarRespuestaSiatAction;
use App\Enums\FacturaEstadoEnum;
use App\Helpers\SiatMetodoPagoHelper;
use App\Models\Cliente;
use App\Models\Configuracion\Configuracion;
use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\PuntoVenta;
use App\Models\Configuracion\Sucursal;
use App\Models\EventoSignificativo;
use App\Models\Factura;
use App\Models\SinMetodoPago;
use App\Models\User;
use App\Repositories\Facturacion\FacturaRepository;
use App\Support\OperationalContextScope;
use Illuminate\Support\Facades\DB;

class FacturaDirectaService
{
    public function __construct(
        private readonly FacturaRepository $repository,
        private readonly ObtenerCuisVigenteAction $obtenerCuisVigente,
        private readonly CuisService $cuisService,
        private readonly CufdService $cufdService,
        private readonly GenerarDatosFacturaDirectaAction $generarDatosFacturaDirecta,
        private readonly GenerarCufFacturaAction $generarCufFactura,
        private readonly GenerarXmlFacturaAction $generarXmlFactura,
        private readonly FirmarXmlFacturaAction $firmarXmlFactura,
        private readonly EnviarFacturaSiatAction $enviarFacturaSiat,
        private readonly RegistrarRespuestaSiatAction $registrarRespuestaSiat,
        private readonly FacturaCorreoService $facturaCorreoService,
        private readonly EventoSignificativoService $eventoSignificativoService,
        private readonly SiatClientService $siatClient,
    ) {}

    public function emitir(array $data, User $user): Factura
    {
        return DB::transaction(function () use ($data, $user): Factura {
            $configuracion = Configuracion::current();
            if (! $configuracion) {
                abort(422, 'No existe una configuracion general registrada para facturar.');
            }

            $empresa = Empresa::query()->firstOrFail();
            $cliente = Cliente::query()->findOrFail($data['cliente_id']);
            $sucursal = Sucursal::query()->findOrFail($data['sucursal_id']);
            $puntoVenta = PuntoVenta::query()->findOrFail($data['punto_venta_id']);
            $ambiente = $configuracion->ambiente_facturacion ?: 'piloto';

            OperationalContextScope::authorize($user, (int) $sucursal->id, (int) $puntoVenta->id);

            $this->cuisService->generarSiNoExiste($sucursal->id, $puntoVenta->id, (int) $user->id);
            $cuis = ($this->obtenerCuisVigente)($sucursal->id, $puntoVenta->id, $ambiente);
            $eventoActivo = $this->eventoSignificativoService->eventoActivoPorContexto(
                (int) $sucursal->id,
                (int) $puntoVenta->id,
                $ambiente,
            );
            $emisionOffline = $eventoActivo instanceof EventoSignificativo;
            $codigoEmision = $emisionOffline ? 2 : 1;

            $cufd = $emisionOffline
                ? $eventoActivo->cufdEvento
                : $this->cufdService->obtenerVigenteORegistrar($sucursal->id, $puntoVenta->id, (int) $user->id, $user);
            $cafc = $emisionOffline && (string) $eventoActivo->tipo_contingencia === 'manual'
                ? $eventoActivo->cafc
                : null;

            if (! $cuis) {
                abort(422, 'Debe existir un CUIS vigente para emitir la factura.');
            }

            if (! $emisionOffline) {
                $this->validarNitClienteSiCorresponde($cliente, $sucursal, $puntoVenta);
            }

            if (! $cufd) {
                abort(422, $emisionOffline
                    ? 'La contingencia activa no tiene un CUFD asociado para emitir fuera de linea.'
                    : 'Debe existir un CUFD vigente para emitir la factura.');
            }

            if ($emisionOffline && (string) $eventoActivo->tipo_contingencia === 'manual' && ! $cafc) {
                abort(422, 'La contingencia manual activa no tiene un CAFC asociado.');
            }

            $numeroFactura = $cafc
                ? $this->repository->getNextCafcInvoiceNumber($cafc)
                : $this->repository->getNextInvoiceNumber(
                    $sucursal->id,
                    $puntoVenta->id,
                    $ambiente,
                    (int) $configuracion->tipo_facturacion,
                );
            $fechaEmision = now(config('app.timezone'));
            $payload = ($this->generarDatosFacturaDirecta)(
                $cliente,
                $sucursal,
                $puntoVenta,
                $user,
                $configuracion,
                $empresa,
                $data['detalles'],
                array_merge($data, [
                    'origen' => $data['origen'] ?? 'directa',
                    'numero_factura' => $numeroFactura,
                    'fecha_emision' => $fechaEmision,
                    'cufd' => $cufd->codigo,
                    'codigo_emision' => $codigoEmision,
                    'evento_significativo_id' => $eventoActivo?->id,
                    'cafc' => $cafc?->codigo,
                ]),
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
                abort(422, 'Debes completar los 4 primeros y 4 ultimos digitos de la tarjeta antes de emitir la factura.');
            }

            if (
                SiatMetodoPagoHelper::requiresGiftCardAmount(
                    (string) $payload['cabecera']['codigoMetodoPago'],
                    $metodoPagoFactura?->descripcion,
                )
                && ! filled($payload['cabecera']['montoGiftCard'] ?? null)
            ) {
                abort(422, 'Debes completar el monto gift card antes de emitir la factura.');
            }

            $payload['cabecera']['cuf'] = ($this->generarCufFactura)(
                $payload['cabecera'],
                $cufd,
                (int) $configuracion->tipo_facturacion,
                $codigoEmision,
            );

            $xml = ($this->generarXmlFactura)($payload, $numeroFactura);
            $signedXml = ($this->firmarXmlFactura)($xml['xml'], $numeroFactura, (int) $configuracion->tipo_facturacion);

            $factura = $this->repository->create([
                'origen' => $data['origen'] ?? 'directa',
                'referencia_externa' => $data['referencia_externa'] ?? null,
                'cliente_id' => $cliente->id,
                'sucursal_id' => $sucursal->id,
                'punto_venta_id' => $puntoVenta->id,
                'user_id' => $user->id,
                'cuis_id' => $cuis->id,
                'cufd_id' => $cufd->id,
                'evento_significativo_id' => $eventoActivo?->id,
                'cafc_id' => $cafc?->id,
                'numero_factura' => $numeroFactura,
                'cuf' => $payload['cabecera']['cuf'],
                'codigo_metodo_pago' => $payload['cabecera']['codigoMetodoPago'],
                'numero_tarjeta' => $payload['cabecera']['numeroTarjeta'],
                'monto_gift_card' => $payload['cabecera']['montoGiftCard'],
                'descuento_global' => (float) ($payload['cabecera']['descuentoAdicional'] ?? 0),
                'codigo_documento_identidad' => $payload['cabecera']['codigoTipoDocumentoIdentidad'],
                'codigo_excepcion' => $payload['cabecera']['codigoExcepcion'],
                'leyenda' => $payload['cabecera']['leyenda'],
                'tipo_facturacion' => (int) $configuracion->tipo_facturacion,
                'ambiente_facturacion' => $configuracion->ambiente_facturacion,
                'codigo_emision' => $codigoEmision,
                'hash_xml' => $xml['hash'],
                'xml_fiscal' => $signedXml['xml'],
                'fecha_emision' => $fechaEmision,
                'monto_total' => $payload['cabecera']['montoTotal'],
                'monto_sujeto_iva' => $payload['cabecera']['montoTotalSujetoIva'],
                'estado_factura' => $emisionOffline ? FacturaEstadoEnum::PENDIENTE_ENVIO->value : FacturaEstadoEnum::PENDIENTE->value,
                'estado_sincronizacion' => $emisionOffline ? 'pendiente_paquete' : 'no_aplica',
                'codigo_estado' => $emisionOffline ? 'LOCAL_CONTINGENCIA' : null,
                'descripcion_estado' => $emisionOffline ? 'Factura emitida fuera de linea por contingencia activa. Pendiente de envio por paquete SIAT.' : null,
                'observacion' => $data['observacion'] ?? null,
                'metadata' => $data['metadata'] ?? null,
            ]);

            foreach ($payload['detalle'] as $detalle) {
                $factura->detalles()->create([
                    'articulo_id' => $detalle['articuloId'] ?? null,
                    'actividad_economica' => $detalle['actividadEconomica'],
                    'codigo_producto_sin' => $detalle['codigoProductoSin'],
                    'codigo_producto' => $detalle['codigoProducto'],
                    'descripcion' => $detalle['descripcion'],
                    'cantidad' => $detalle['cantidad'],
                    'unidad_medida' => $detalle['unidadMedida'],
                    'precio_unitario' => $detalle['precioUnitario'],
                    'monto_descuento' => $detalle['montoDescuento'],
                    'subtotal' => $detalle['subTotal'],
                    'numero_serie' => $detalle['numeroSerie'],
                    'numero_imei' => $detalle['numeroImei'],
                ]);
            }

            if ($emisionOffline) {
                DB::afterCommit(fn () => $this->facturaCorreoService->enviarFacturaFueraLinea($factura->refresh()));

                return $factura->load([
                    'detalles',
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

            $response = ($this->enviarFacturaSiat)([
                'factura_id' => $factura->id,
                'xml_content' => $signedXml['xml'],
                'cuis' => $cuis->codigo,
                'cufd' => $cufd->codigo,
                'payload' => $payload,
            ]);

            $factura = ($this->registrarRespuestaSiat)($factura, $response);

            DB::afterCommit(fn () => $this->facturaCorreoService->enviarFacturaEmitida($factura->refresh()));

            return $factura->load([
                'detalles',
                'cliente',
                'sucursal',
                'puntoVenta',
                'user',
                'cuis',
                'cufd',
                'anulaciones.user',
            ]);
        });
    }

    private function validarNitClienteSiCorresponde(Cliente $cliente, Sucursal $sucursal, PuntoVenta $puntoVenta): void
    {
        if ((string) $cliente->tipo_documento_identidad !== '5') {
            return;
        }

        $numeroDocumento = preg_replace('/\D+/', '', (string) $cliente->nit_ci);

        if ($numeroDocumento === '' || preg_match('/^0+$/', $numeroDocumento) === 1) {
            abort(422, 'El cliente tiene tipo de documento NIT, pero el numero no es valido.');
        }

        $response = $this->siatClient->verificarNit([
            'sucursal_id' => $sucursal->id,
            'punto_venta_id' => $puntoVenta->id,
        ], $numeroDocumento);

        if (($response['success'] ?? false) === true) {
            return;
        }

        $message = trim((string) ($response['message'] ?? ''));
        $suffix = $message !== '' ? " Detalle SIAT: {$message}" : '';

        abort(
            422,
            "El numero {$numeroDocumento} esta registrado como NIT, pero SIAT no lo reconoce como NIT valido. Verifica el documento o cambia el tipo de documento del cliente a CI si corresponde.{$suffix}",
        );
    }
}
