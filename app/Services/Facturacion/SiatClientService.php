<?php

namespace App\Services\Facturacion;

use App\Enums\TipoFacturacionEnum;
use App\Models\Configuracion\Configuracion;
use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\PuntoVenta;
use App\Models\Configuracion\Sucursal;
use App\Models\Cufd;
use App\Models\Factura;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use SoapParam;
use SoapVar;

class SiatClientService
{
    public function __construct(
        private readonly SiatEndpointResolver $endpointResolver,
        private readonly SiatSoapTransportService $transport,
    ) {
    }

    public function sincronizarCatalogos(array $context): array
    {
        $prepared = $this->buildOperationalContext($context, requireCuis: true);

        if (! ($prepared['success'] ?? false)) {
            return $prepared;
        }

        $endpoint = $this->endpointResolver->resolve('sincronizacion', $prepared['configuracion']);
        $payload = $this->buildSyncPayload($prepared);
        $results = [];
        $catalogos = [];

        foreach (config('siat.catalogs', []) as $catalogKey => $catalogConfig) {
            $operations = $this->operations("siat.soap_methods.sincronizacion.{$catalogConfig['method']}");

            if ($operations === []) {
                $results[$catalogKey] = [
                    'success' => false,
                    'code' => 'CATALOG_METHOD_NOT_CONFIGURED',
                    'message' => "No existe una operacion SOAP configurada para {$catalogKey}.",
                ];
                continue;
            }

            $response = $this->callWithFallback($endpoint, $operations, $payload, $prepared['auth']);
            $normalized = $this->normalizeSiatResponse($response);
            $results[$catalogKey] = $normalized;
            $catalogos[$catalogKey] = $this->extractCatalogRows($normalized['raw'] ?? [], (array) ($catalogConfig['response_keys'] ?? []), $catalogKey);
        }

        $success = collect($results)->every(fn ($item) => ($item['success'] ?? false) === true);

        return [
            'success' => $success,
            'code' => $success ? 'SINCRONIZACION_OK' : 'SINCRONIZACION_PARCIAL',
            'message' => $success
                ? 'Sincronizacion SIAT ejecutada correctamente.'
                : 'La sincronizacion se ejecuto con observaciones. Revise el detalle por catalogo.',
            'endpoint' => $endpoint,
            'catalogos' => $catalogos,
            'detalle' => $results,
            'context' => $prepared['context'],
        ];
    }

    public function solicitarCuis(array $context): array
    {
        $prepared = $this->buildOperationalContext($context);

        if (! ($prepared['success'] ?? false)) {
            return $prepared;
        }

        $endpoint = $this->endpointResolver->resolve('codigos', $prepared['configuracion']);
        $payload = $this->buildCodigoPayload($prepared);

        return $this->normalizeSiatResponse(
            $this->callWithFallback(
                $endpoint,
                $this->operations('siat.soap_methods.codigos.solicitud_cuis'),
                $payload,
                $prepared['auth'],
            ),
            [
                'codigo_field' => ['codigoCUIS', 'cuis', 'codigo'],
                'fecha_field' => ['fechaVigencia'],
            ],
        );
    }

    public function solicitarCufd(array $context): array
    {
        $prepared = $this->buildOperationalContext($context, requireCuis: true);

        if (! ($prepared['success'] ?? false)) {
            return $prepared;
        }

        $endpoint = $this->endpointResolver->resolve('codigos', $prepared['configuracion']);
        $payload = array_merge($this->buildCodigoPayload($prepared), [
            'cuis' => $prepared['cuis'],
        ]);

        return $this->normalizeSiatResponse(
            $this->callWithFallback(
                $endpoint,
                $this->operations('siat.soap_methods.codigos.solicitud_cufd'),
                $payload,
                $prepared['auth'],
            ),
            [
                'codigo_field' => ['codigoCUFD', 'cufd', 'codigo'],
                'fecha_field' => ['fechaVigencia'],
                'codigo_control_field' => ['codigoControl'],
                'direccion_field' => ['direccion'],
            ],
        );
    }

    public function emitirFactura(array $payload): array
    {
        $factura = Factura::query()->with(['venta.cliente', 'sucursal', 'puntoVenta', 'cuis', 'cufd'])->find($payload['factura_id'] ?? null);

        if (! $factura) {
            return $this->error('FACTURA_NOT_FOUND', 'No se pudo cargar la factura para enviarla al SIAT.');
        }

        $configuracion = Configuracion::query()->first();
        $endpoint = $this->endpointResolver->facturacionModule($configuracion?->tipo_facturacion, $configuracion);

        if (! ($endpoint['configured'] ?? false)) {
            return $this->error('SIAT_ENDPOINT_NOT_CONFIGURED', 'El endpoint de facturacion del ambiente activo no esta configurado.', ['endpoint' => $endpoint]);
        }

        $xmlContent = isset($payload['xml_content']) ? (string) $payload['xml_content'] : null;
        $xmlPath = (string) ($payload['xml_path'] ?? '');
        $xmlAbsolutePath = $xmlPath !== '' && Storage::disk('local')->exists($xmlPath)
            ? Storage::disk('local')->path($xmlPath)
            : $xmlPath;

        if (($xmlContent === null || $xmlContent === '') && ($xmlAbsolutePath === '' || ! is_file($xmlAbsolutePath))) {
            return $this->error('XML_NOT_FOUND', 'No se encontro el XML firmado para la emision.', ['endpoint' => $endpoint]);
        }

        $resolvedXmlContent = $xmlContent !== null && $xmlContent !== ''
            ? $xmlContent
            : (string) file_get_contents($xmlAbsolutePath);

        $archivo = $this->buildEncodedInvoicePayload($resolvedXmlContent);

        $request = [
            'codigoAmbiente' => $this->environmentCode($configuracion),
            'codigoPuntoVenta' => (int) ($factura->puntoVenta?->codigo ?? 0),
            'codigoSistema' => (string) ($configuracion?->codigo_sistema ?? ''),
            'codigoSucursal' => (int) ($factura->sucursal?->codigo ?? 0),
            'nit' => (int) preg_replace('/\D+/', '', (string) optional(Empresa::query()->first())->nit),
            'codigoDocumentoSector' => 1,
            'codigoEmision' => 1,
            'codigoModalidad' => $this->modalidadCode($configuracion),
            'cufd' => (string) ($factura->cufd?->codigo ?? ''),
            'cuis' => (string) ($factura->cuis?->codigo ?? ''),
            'tipoFacturaDocumento' => 1,
            'archivo' => $archivo,
            'fechaEnvio' => now(config('app.timezone'))->format('Y-m-d\TH:i:s.v'),
            'hashArchivo' => $this->hashEncodedInvoicePayload($archivo),
        ];

        return $this->normalizeSiatResponse(
            $this->callWithFallback(
                $endpoint,
                $this->operations('siat.soap_methods.facturacion.recepcion_factura'),
                $request,
                $this->authData($configuracion),
            ),
            [
                'codigo_field' => 'codigoRecepcion',
                'cuf_field' => 'cuf',
            ],
        );
    }

    public function recepcionPaqueteFactura(array $payload): array
    {
        $prepared = $this->buildOperationalContext($payload, requireCuis: true, requireCufd: true);

        if (! ($prepared['success'] ?? false)) {
            return $prepared;
        }

        $configuracion = $prepared['configuracion'];
        $endpoint = $this->endpointResolver->facturacionModule($configuracion?->tipo_facturacion, $configuracion);

        if (! ($endpoint['configured'] ?? false)) {
            return $this->error('SIAT_ENDPOINT_NOT_CONFIGURED', 'El endpoint de facturacion del ambiente activo no esta configurado.', ['endpoint' => $endpoint]);
        }

        $request = array_merge(
            $this->buildFacturaSoapBasePayload($prepared, 2),
            [
                'archivo' => $payload['archivo'] ?? null,
                'fechaEnvio' => $this->formatSiatDate($payload['fecha_envio'] ?? now(config('app.timezone'))),
                'hashArchivo' => (string) ($payload['hash_archivo'] ?? ''),
                'cantidadFacturas' => (int) ($payload['cantidad_facturas'] ?? 0),
                'codigoEvento' => (string) ($payload['codigo_recepcion_evento'] ?? ''),
            ],
            filled($payload['cafc'] ?? null)
                ? ['cafc' => (string) $payload['cafc']]
                : [],
        );

        return $this->normalizeSiatResponse(
            $this->callWithFallback(
                $endpoint,
                $this->operations('siat.soap_methods.facturacion.recepcion_paquete_factura'),
                $request,
                $prepared['auth'],
            ),
            [
                'codigo_field' => ['codigoRecepcion'],
            ],
        );
    }

    public function validacionRecepcionPaqueteFactura(array $payload): array
    {
        $prepared = $this->buildOperationalContext($payload, requireCuis: true, requireCufd: true);

        if (! ($prepared['success'] ?? false)) {
            return $prepared;
        }

        $configuracion = $prepared['configuracion'];
        $endpoint = $this->endpointResolver->facturacionModule($configuracion?->tipo_facturacion, $configuracion);

        if (! ($endpoint['configured'] ?? false)) {
            return $this->error('SIAT_ENDPOINT_NOT_CONFIGURED', 'El endpoint de facturacion del ambiente activo no esta configurado.', ['endpoint' => $endpoint]);
        }

        $request = array_merge(
            $this->buildFacturaSoapBasePayload($prepared, 2),
            [
                'codigoRecepcion' => (string) ($payload['codigo_recepcion'] ?? ''),
            ],
        );

        return $this->normalizeSiatResponse(
            $this->callWithFallback(
                $endpoint,
                $this->operations('siat.soap_methods.facturacion.validacion_recepcion_paquete_factura'),
                $request,
                $prepared['auth'],
            ),
            [
                'codigo_field' => ['codigoRecepcion'],
            ],
        );
    }

    public function consultarFactura(array $payload): array
    {
        $factura = Factura::query()->with(['sucursal', 'puntoVenta', 'cuis', 'cufd'])->find($payload['factura_id'] ?? null);

        if (! $factura) {
            return $this->error('FACTURA_NOT_FOUND', 'No se pudo cargar la factura para consultar su estado.');
        }

        $configuracion = Configuracion::query()->first();
        $endpoint = $this->endpointResolver->facturacionModule($configuracion?->tipo_facturacion, $configuracion);

        return $this->normalizeSiatResponse(
            $this->callWithFallback(
                $endpoint,
                $this->operations('siat.soap_methods.facturacion.verificacion_estado_factura'),
                [
                    'codigoAmbiente' => $this->environmentCode($configuracion),
                    'codigoPuntoVenta' => (int) ($factura->puntoVenta?->codigo ?? 0),
                    'codigoSistema' => (string) ($configuracion?->codigo_sistema ?? ''),
                    'codigoSucursal' => (int) ($factura->sucursal?->codigo ?? 0),
                    'nit' => (int) preg_replace('/\D+/', '', (string) optional(Empresa::query()->first())->nit),
                    'codigoDocumentoSector' => 1,
                    'codigoEmision' => 1,
                    'codigoModalidad' => $this->modalidadCode($configuracion),
                    'cufd' => (string) ($factura->cufd?->codigo ?? ''),
                    'cuis' => (string) ($factura->cuis?->codigo ?? ''),
                    'tipoFacturaDocumento' => 1,
                    'codigoRecepcion' => (string) ($factura->codigo_recepcion ?? ''),
                    'cuf' => (string) ($factura->cuf ?? ''),
                ],
                $this->authData($configuracion),
            ),
        );
    }

    public function anularFactura(array $payload): array
    {
        $factura = Factura::query()->with(['sucursal', 'puntoVenta', 'cuis', 'cufd'])->find($payload['factura_id'] ?? null);

        if (! $factura) {
            return $this->error('FACTURA_NOT_FOUND', 'No se pudo cargar la factura para anularla.');
        }

        $configuracion = Configuracion::query()->first();
        $endpoint = $this->endpointResolver->facturacionModule($configuracion?->tipo_facturacion, $configuracion);

        return $this->normalizeSiatResponse(
            $this->callWithFallback(
                $endpoint,
                $this->operations('siat.soap_methods.facturacion.anulacion_factura'),
                [
                    'codigoAmbiente' => $this->environmentCode($configuracion),
                    'codigoPuntoVenta' => (int) ($factura->puntoVenta?->codigo ?? 0),
                    'codigoSistema' => (string) ($configuracion?->codigo_sistema ?? ''),
                    'codigoSucursal' => (int) ($factura->sucursal?->codigo ?? 0),
                    'nit' => (int) preg_replace('/\D+/', '', (string) optional(Empresa::query()->first())->nit),
                    'codigoDocumentoSector' => 1,
                    'codigoEmision' => 1,
                    'codigoModalidad' => $this->modalidadCode($configuracion),
                    'cufd' => (string) ($factura->cufd?->codigo ?? ''),
                    'cuis' => (string) ($factura->cuis?->codigo ?? ''),
                    'tipoFacturaDocumento' => 1,
                    'codigoMotivo' => (int) ($payload['codigo_motivo_anulacion'] ?? 0),
                    'cuf' => (string) ($factura->cuf ?? ''),
                ],
                $this->authData($configuracion),
            ),
        );
    }

    public function revertirAnulacionFactura(array $payload): array
    {
        $factura = Factura::query()->with(['sucursal', 'puntoVenta', 'cuis', 'cufd'])->find($payload['factura_id'] ?? null);

        if (! $factura) {
            return $this->error('FACTURA_NOT_FOUND', 'No se pudo cargar la factura para revertir su anulacion.');
        }

        $configuracion = Configuracion::query()->first();
        $endpoint = $this->endpointResolver->facturacionModule($configuracion?->tipo_facturacion, $configuracion);

        return $this->normalizeSiatResponse(
            $this->callWithFallback(
                $endpoint,
                $this->operations('siat.soap_methods.facturacion.reversion_anulacion_factura'),
                [
                    'codigoAmbiente' => $this->environmentCode($configuracion),
                    'codigoPuntoVenta' => (int) ($factura->puntoVenta?->codigo ?? 0),
                    'codigoSistema' => (string) ($configuracion?->codigo_sistema ?? ''),
                    'codigoSucursal' => (int) ($factura->sucursal?->codigo ?? 0),
                    'nit' => (int) preg_replace('/\D+/', '', (string) optional(Empresa::query()->first())->nit),
                    'codigoDocumentoSector' => 1,
                    'codigoEmision' => 1,
                    'codigoModalidad' => $this->modalidadCode($configuracion),
                    'cufd' => (string) ($factura->cufd?->codigo ?? ''),
                    'cuis' => (string) ($factura->cuis?->codigo ?? ''),
                    'tipoFacturaDocumento' => 1,
                    'cuf' => (string) ($factura->cuf ?? ''),
                ],
                $this->authData($configuracion),
            ),
        );
    }

    public function registrarEventoSignificativo(array $payload): array
    {
        $prepared = $this->buildOperationalContext($payload, requireCuis: true, requireCufd: true);

        if (! ($prepared['success'] ?? false)) {
            return $prepared;
        }

        $endpoint = $this->endpointResolver->resolve('operaciones', $prepared['configuracion']);

        return $this->normalizeSiatResponse(
            $this->callWithFallback(
                $endpoint,
                $this->operations('siat.soap_methods.operaciones.registro_evento_significativo'),
                [
                    'codigoAmbiente' => $this->environmentCode($prepared['configuracion']),
                    'codigoMotivoEvento' => (int) ($payload['codigo_evento'] ?? 0),
                    'codigoPuntoVenta' => $prepared['context']['codigo_punto_venta'],
                    'codigoSistema' => (string) $prepared['configuracion']->codigo_sistema,
                    'codigoSucursal' => $prepared['context']['codigo_sucursal'],
                    'cufd' => (string) $prepared['cufd'],
                    'cufdEvento' => (string) ($payload['cufd_evento'] ?? $prepared['cufd']),
                    'cuis' => (string) $prepared['cuis'],
                    'descripcion' => (string) ($payload['descripcion'] ?? ''),
                    'fechaHoraFinEvento' => $this->formatSiatDate($payload['fecha_fin'] ?? null),
                    'fechaHoraInicioEvento' => $this->formatSiatDate($payload['fecha_inicio'] ?? null),
                    'nit' => (int) preg_replace('/\D+/', '', (string) $prepared['empresa']->nit),
                ],
                $prepared['auth'],
            ),
            [
                'codigo_field' => ['codigoRecepcionEventoSignificativo', 'codigoRecepcion'],
            ],
        );
    }

    public function profile(): array
    {
        return $this->endpointResolver->profile();
    }

    private function buildOperationalContext(array $context, bool $requireCuis = false, bool $requireCufd = false): array
    {
        $configuracion = Configuracion::query()->first();
        $empresa = Empresa::query()->first();

        if (! $configuracion || ! $empresa) {
            return $this->error('SIAT_CONFIG_MISSING', 'Debe existir una empresa y configuracion validas para operar con SIAT.');
        }

        if (! $configuracion->facturacion_habilitada) {
            return $this->error('SIAT_DISABLED', 'La facturacion no esta habilitada en la configuracion del sistema.');
        }

        if (blank($configuracion->codigo_sistema) || blank($configuracion->tokenSiatActivo())) {
            return $this->error('SIAT_CREDENTIALS_MISSING', 'Debe registrar token SIAT y codigo de sistema para consumir servicios reales.');
        }

        $sucursal = Sucursal::query()->find($context['sucursal_id'] ?? null);
        $puntoVenta = PuntoVenta::query()->find($context['punto_venta_id'] ?? null);

        if (! $sucursal || ! $puntoVenta) {
            return $this->error('SIAT_CONTEXT_INVALID', 'Debe seleccionar una sucursal y punto de venta validos.');
        }

        $cuis = null;
        $cufd = null;

        if ($requireCuis || $requireCufd) {
            $cuis = \App\Models\Cuis::query()
                ->where('sucursal_id', $sucursal->id)
                ->where('punto_venta_id', $puntoVenta->id)
                ->where('estado', true)
                ->latest('fecha_vigencia')
                ->value('codigo');
        }

        if ($requireCufd) {
            $ambiente = $configuracion->ambiente_facturacion ?: 'piloto';
            $cufd = Cufd::query()
                ->where('sucursal_id', $sucursal->id)
                ->where('punto_venta_id', $puntoVenta->id)
                ->where('ambiente_facturacion', $ambiente)
                ->where('estado', true)
                ->latest('fecha_vigencia')
                ->value('codigo');
        }

        if ($requireCuis && blank($cuis)) {
            return $this->error('SIAT_CUIS_MISSING', 'No existe un CUIS vigente para el contexto seleccionado.');
        }

        if ($requireCufd && blank($cufd)) {
            return $this->error('SIAT_CUFD_MISSING', 'No existe un CUFD vigente para el contexto seleccionado.');
        }

        return [
            'success' => true,
            'configuracion' => $configuracion,
            'empresa' => $empresa,
            'cuis' => $cuis,
            'cufd' => $cufd,
            'auth' => $this->authData($configuracion),
            'context' => [
                'sucursal_id' => $sucursal->id,
                'punto_venta_id' => $puntoVenta->id,
                'codigo_sucursal' => (int) $sucursal->codigo,
                'codigo_punto_venta' => (int) $puntoVenta->codigo,
            ],
        ];
    }

    private function buildCodigoPayload(array $prepared): array
    {
        return [
            'codigoAmbiente' => $this->environmentCode($prepared['configuracion']),
            'codigoSistema' => (string) $prepared['configuracion']->codigo_sistema,
            'nit' => (int) preg_replace('/\D+/', '', (string) $prepared['empresa']->nit),
            'codigoModalidad' => $this->modalidadCode($prepared['configuracion']),
            'codigoSucursal' => $prepared['context']['codigo_sucursal'],
            'codigoPuntoVenta' => $prepared['context']['codigo_punto_venta'],
        ];
    }

    private function buildSyncPayload(array $prepared): array
    {
        return [
            'codigoAmbiente' => $this->environmentCode($prepared['configuracion']),
            'codigoSistema' => (string) $prepared['configuracion']->codigo_sistema,
            'codigoSucursal' => $prepared['context']['codigo_sucursal'],
            'codigoPuntoVenta' => $prepared['context']['codigo_punto_venta'],
            'cuis' => (string) $prepared['cuis'],
            'nit' => (int) preg_replace('/\D+/', '', (string) $prepared['empresa']->nit),
        ];
    }

    private function buildFacturaSoapBasePayload(array $prepared, int $codigoEmision): array
    {
        return [
            'codigoAmbiente' => $this->environmentCode($prepared['configuracion']),
            'codigoDocumentoSector' => 1,
            'codigoEmision' => $codigoEmision,
            'codigoModalidad' => $this->modalidadCode($prepared['configuracion']),
            'codigoPuntoVenta' => $prepared['context']['codigo_punto_venta'],
            'codigoSistema' => (string) $prepared['configuracion']->codigo_sistema,
            'codigoSucursal' => $prepared['context']['codigo_sucursal'],
            'cufd' => (string) $prepared['cufd'],
            'cuis' => (string) $prepared['cuis'],
            'nit' => (int) preg_replace('/\D+/', '', (string) $prepared['empresa']->nit),
            'tipoFacturaDocumento' => 1,
        ];
    }

    private function authData(?Configuracion $configuracion): array
    {
        $profile = $this->endpointResolver->profile($configuracion);

        return [
            'token' => (string) ($configuracion?->tokenSiatActivo() ?? ''),
            'token_header' => $profile['auth']['token_header'] ?? 'Authorization',
            'token_prefix' => $profile['auth']['token_prefix'] ?? 'Token',
        ];
    }

    private function environmentCode(?Configuracion $configuracion): int
    {
        return (int) ($this->endpointResolver->profile($configuracion)['environment']['codigo_ambiente'] ?? 2);
    }

    private function modalidadCode(?Configuracion $configuracion): int
    {
        return match ((int) ($configuracion?->tipo_facturacion ?? TipoFacturacionEnum::NO_EMITE->value)) {
            TipoFacturacionEnum::ELECTRONICA->value => 1,
            TipoFacturacionEnum::COMPUTARIZADA->value => 2,
            default => 2,
        };
    }

    private function normalizeSiatResponse(array $transportResponse, array $overrides = []): array
    {
        if (! ($transportResponse['success'] ?? false)) {
            return $transportResponse;
        }

        $raw = $transportResponse['raw'] ?? [];
        $success = $this->toBooleanValue($this->findFirstValueByKey($raw, ['transaccion']));
        $messages = $this->extractMessages($raw);

        return [
            'success' => $success,
            'code' => (string) ($this->findFirstValueByKey($raw, ['codigoEstado']) ?? ($success ? 'SIAT_OK' : 'SIAT_OBSERVADA')),
            'message' => $messages !== [] ? implode(' | ', $messages) : ($success ? 'Operacion SIAT procesada correctamente.' : 'SIAT devolvio observaciones.'),
            'codigo' => $this->findFirstValueByKey($raw, $this->fieldKeys($overrides['codigo_field'] ?? 'codigo')),
            'fecha_vigencia' => $this->findFirstValueByKey($raw, $this->fieldKeys($overrides['fecha_field'] ?? 'fechaVigencia')),
            'codigo_control' => $this->findFirstValueByKey($raw, $this->fieldKeys($overrides['codigo_control_field'] ?? 'codigoControl')),
            'direccion' => $this->findFirstValueByKey($raw, $this->fieldKeys($overrides['direccion_field'] ?? 'direccion')),
            'codigo_recepcion' => $this->findFirstValueByKey($raw, ['codigoRecepcion']),
            'cuf' => $this->findFirstValueByKey($raw, $this->fieldKeys($overrides['cuf_field'] ?? 'cuf')),
            'raw' => $raw,
            'endpoint' => $transportResponse['endpoint'] ?? null,
            'operation' => $transportResponse['operation'] ?? null,
        ];
    }

    private function extractMessages(array $raw): array
    {
        $messages = $this->findFirstArrayByKey($raw, ['mensajesList', 'codigosRespuestas']);

        if (! is_array($messages)) {
            return [];
        }

        return collect($messages)
            ->map(function ($message) {
                if (! is_array($message)) {
                    return null;
                }

                return $message['descripcion'] ?? $message['mensaje'] ?? null;
            })
            ->filter()
            ->values()
            ->all();
    }

    private function extractCatalogRows(array $raw, array $preferredKeys, string $catalogKey): array
    {
        $records = $this->findFirstArrayByKey($raw, $preferredKeys);

        if (! is_array($records)) {
            return [];
        }

        return collect($records)
            ->map(fn ($record, $index) => $this->mapCatalogRecord(is_array($record) ? $record : [], $catalogKey, $index))
            ->filter()
            ->values()
            ->all();
    }

    private function mapCatalogRecord(array $record, string $catalogKey, int|string $index): ?array
    {
        return match ($catalogKey) {
            'actividades' => [
                'codigo_clasificador' => (string) ($record['codigoCaeb'] ?? $record['codigoActividad'] ?? $record['codigoClasificador'] ?? ''),
                'descripcion' => (string) ($record['descripcion'] ?? ''),
                'estado' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ],
            'productos_servicios' => [
                'codigo_actividad' => (string) ($record['codigoActividad'] ?? $record['codigoCaeb'] ?? ''),
                'codigo_producto' => (string) ($record['codigoProducto'] ?? $record['codigoClasificador'] ?? ''),
                'descripcion' => (string) ($record['descripcionProducto'] ?? $record['descripcion'] ?? ''),
                'estado' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ],
            'leyendas' => [
                'codigo_actividad' => (string) ($record['codigoActividad'] ?? ''),
                'descripcion_leyenda' => (string) ($record['descripcionLeyenda'] ?? $record['descripcion'] ?? ''),
                'estado' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ],
            default => [
                'codigo_clasificador' => (string) ($record['codigoClasificador'] ?? $record['codigo'] ?? ''),
                'descripcion' => (string) ($record['descripcion'] ?? ''),
                'estado' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        };
    }

    private function findFirstArrayByKey(array $data, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                return $this->forceList($data[$key]);
            }
        }

        foreach ($data as $value) {
            if (is_array($value)) {
                $found = $this->findFirstArrayByKey($value, $keys);

                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }

    private function findFirstValueByKey(array $data, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data) && ! is_array($data[$key])) {
                return $data[$key];
            }
        }

        foreach ($data as $branchKey => $value) {
            if (in_array((string) $branchKey, ['mensajesList', 'codigosRespuestas'], true)) {
                continue;
            }

            if (is_array($value)) {
                $found = $this->findFirstValueByKey($value, $keys);

                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function fieldKeys(string|array $keys): array
    {
        return is_array($keys) ? array_values($keys) : [$keys];
    }

    private function forceList(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if ($value === []) {
            return [];
        }

        $isAssoc = array_keys($value) !== range(0, count($value) - 1);

        return $isAssoc ? [$value] : $value;
    }

    private function formatSiatDate(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return \Carbon\Carbon::parse((string) $value, config('app.timezone'))
            ->timezone(config('app.timezone'))
            ->format('Y-m-d\TH:i:s.v');
    }

    private function buildEncodedInvoicePayload(string $xmlContent): string
    {
        // SIAT espera el XML comprimido en GZIP binario; SoapClient serializa el xsd:base64Binary.
        return gzencode($xmlContent, 9, FORCE_GZIP);
    }

    private function hashEncodedInvoicePayload(string $encodedPayload): string
    {
        // SIAT solicita el SHA-256 del archivo Gzip enviado en la etiqueta archivo.
        return hash('sha256', $encodedPayload);
    }

    private function error(string $code, string $message, array $extra = []): array
    {
        return array_merge([
            'success' => false,
            'code' => $code,
            'message' => $message,
        ], $extra);
    }

    private function callWithFallback(array $endpoint, array $operations, ?array $payload, array $auth): array
    {
        $lastResponse = null;

        foreach ($operations as $operation) {
            foreach ($this->payloadCandidates($operation, $payload) as $candidate) {
                $response = $this->transport->call($endpoint, $operation, $candidate['payload'], $auth);
                $response['candidate'] = $candidate['label'];
                $response['payload_debug'] = $this->debugPayload($candidate['payload']);
                $this->logSoapAttempt($operation, $candidate, $endpoint, $response);
                $lastResponse = $response;

                $message = strtolower((string) ($response['message'] ?? ''));

                if (($response['success'] ?? false) === true) {
                    return $response;
                }

                if (
                    ! str_contains($message, 'is not a valid method for this service')
                    && ! str_contains($message, "has no 'solicitudcuis' property")
                    && ! str_contains($message, "has no 'solicitudcufd' property")
                    && ! str_contains($message, "has no 'solicitudsincronizacion' property")
                    && ! str_contains($message, "has no 'solicitudeventosignificativo' property")
                    && ! str_contains($message, "has no 'solicitudserviciorecepcionfactura' property")
                    && ! str_contains($message, "has no 'solicitudserviciorecepcionpaquete' property")
                    && ! str_contains($message, "has no 'solicitudserviciovalidacionrecepcionpaquete' property")
                    && ! str_contains($message, "has no 'solicitudservicioverificacionestadofactura' property")
                    && ! str_contains($message, "has no 'solicitudservicioanulacionfactura' property")
                    && ! str_contains($message, "has no 'solicitudservicioreversionanulacionfactura' property")
                    && ! str_contains($message, 'encoding: object has no')
                ) {
                    return $response;
                }
            }
        }

        return $lastResponse ?? $this->error('SOAP_OPERATION_NOT_CONFIGURED', 'No hay operaciones SOAP configuradas para este servicio.');
    }

    /**
     * @return array<int, string>
     */
    private function operations(string $configKey): array
    {
        $value = config($configKey);

        if (is_array($value)) {
            return array_values(array_filter($value, fn ($item) => is_string($item) && $item !== ''));
        }

        return is_string($value) && $value !== '' ? [$value] : [];
    }

    /**
     * @return array<int, array{label: string, payload: mixed}>
     */
    private function payloadCandidates(string $operation, ?array $payload): array
    {
        if ($payload === null) {
            return [['label' => 'empty', 'payload' => null]];
        }

        $payloadObject = (object) $payload;
        $syncSoapVar = new SoapVar(
            $payloadObject,
            SOAP_ENC_OBJECT,
            null,
            null,
            'SolicitudSincronizacion',
            'https://siat.impuestos.gob.bo/'
        );
        $recepcionSoapVar = new SoapVar(
            $payloadObject,
            SOAP_ENC_OBJECT,
            null,
            null,
            'SolicitudServicioRecepcionFactura',
            'https://siat.impuestos.gob.bo/'
        );
        $recepcionPaqueteSoapVar = new SoapVar(
            $payloadObject,
            SOAP_ENC_OBJECT,
            null,
            null,
            'SolicitudServicioRecepcionPaquete',
            'https://siat.impuestos.gob.bo/'
        );
        $validacionPaqueteSoapVar = new SoapVar(
            $payloadObject,
            SOAP_ENC_OBJECT,
            null,
            null,
            'SolicitudServicioValidacionRecepcionPaquete',
            'https://siat.impuestos.gob.bo/'
        );
        $recepcionWrapperObject = (object) ['SolicitudServicioRecepcionFactura' => $payloadObject];
        $recepcionWrapperLowerObject = (object) ['solicitudServicioRecepcionFactura' => $payloadObject];
        $recepcionPaqueteWrapperObject = (object) ['SolicitudServicioRecepcionPaquete' => $payloadObject];
        $recepcionPaqueteWrapperLowerObject = (object) ['solicitudServicioRecepcionPaquete' => $payloadObject];
        $validacionPaqueteWrapperObject = (object) ['SolicitudServicioValidacionRecepcionPaquete' => $payloadObject];
        $validacionPaqueteWrapperLowerObject = (object) ['solicitudServicioValidacionRecepcionPaquete' => $payloadObject];
        $verificacionSoapVar = new SoapVar(
            $payloadObject,
            SOAP_ENC_OBJECT,
            null,
            null,
            'SolicitudServicioVerificacionEstadoFactura',
            'https://siat.impuestos.gob.bo/'
        );
        $anulacionSoapVar = new SoapVar(
            $payloadObject,
            SOAP_ENC_OBJECT,
            null,
            null,
            'SolicitudServicioAnulacionFactura',
            'https://siat.impuestos.gob.bo/'
        );
        $reversionAnulacionSoapVar = new SoapVar(
            $payloadObject,
            SOAP_ENC_OBJECT,
            null,
            null,
            'SolicitudServicioReversionAnulacionFactura',
            'https://siat.impuestos.gob.bo/'
        );

        return match (strtolower($operation)) {
            'cuis', 'solicitudcuis' => [
                ['label' => 'wrapper_solicitud_cuis', 'payload' => ['SolicitudCuis' => $payload]],
                ['label' => 'wrapper_solicitud_cuis_lower', 'payload' => ['solicitudCuis' => $payload]],
                ['label' => 'plain_payload', 'payload' => $payload],
            ],
            'cufd', 'solicitudcufd' => [
                ['label' => 'wrapper_solicitud_cufd', 'payload' => ['SolicitudCufd' => $payload]],
                ['label' => 'wrapper_solicitud_cufd_lower', 'payload' => ['solicitudCufd' => $payload]],
                ['label' => 'plain_payload', 'payload' => $payload],
            ],
            'sincronizaractividades',
            'sincronizarlistaproductosservicios',
            'sincronizarparametricamotivoanulacion',
            'sincronizarlistaleyendasfactura',
            'sincronizarlistaleyendas',
            'sincronizarparametricatipodocumentoidentidad',
            'sincronizarparametricaunidadmedida',
            'sincronizarparametricatipometodopago' => [
                ['label' => 'wrapper_solicitud_sincronizacion', 'payload' => ['SolicitudSincronizacion' => $payload]],
                ['label' => 'soapvar_solicitud_sincronizacion', 'payload' => [$syncSoapVar]],
                ['label' => 'soapparam_solicitud_sincronizacion', 'payload' => [new SoapParam($payloadObject, 'SolicitudSincronizacion')]],
                ['label' => 'plain_payload', 'payload' => $payload],
                ['label' => 'wrapper_solicitud_sincronizacion_lower', 'payload' => ['solicitudSincronizacion' => $payload]],
                ['label' => 'wrapper_operation_ucfirst', 'payload' => [ucfirst($operation) => $payload]],
            ],
            'sincronizarparametricamoneda',
            'sincronizarparametricatipomoneda' => [
                ['label' => 'wrapper_solicitud_sincronizacion', 'payload' => ['SolicitudSincronizacion' => $payload]],
                ['label' => 'soapvar_solicitud_sincronizacion', 'payload' => [$syncSoapVar]],
                ['label' => 'soapparam_solicitud_sincronizacion', 'payload' => [new SoapParam($payloadObject, 'SolicitudSincronizacion')]],
                ['label' => 'plain_payload', 'payload' => $payload],
                ['label' => 'wrapper_operation_ucfirst', 'payload' => [ucfirst($operation) => $payload]],
                ['label' => 'wrapper_operation_exact', 'payload' => [$operation => $payload]],
            ],
            'registroeventosignificativo' => [
                ['label' => 'wrapper_solicitud_evento', 'payload' => ['SolicitudEventoSignificativo' => $payload]],
                ['label' => 'wrapper_solicitud_evento_lower', 'payload' => ['solicitudEventoSignificativo' => $payload]],
                ['label' => 'soapparam_solicitud_evento', 'payload' => [new SoapParam($payloadObject, 'SolicitudEventoSignificativo')]],
                ['label' => 'plain_payload', 'payload' => $payload],
            ],
            'recepcionfactura' => [
                ['label' => 'wrapper_recepcion_factura', 'payload' => ['SolicitudServicioRecepcionFactura' => $payload]],
                ['label' => 'wrapper_recepcion_factura_object', 'payload' => ['SolicitudServicioRecepcionFactura' => $payloadObject]],
                ['label' => 'wrapper_recepcion_factura_stdclass', 'payload' => $recepcionWrapperObject],
                ['label' => 'wrapper_recepcion_factura_lower', 'payload' => ['solicitudServicioRecepcionFactura' => $payload]],
                ['label' => 'wrapper_recepcion_factura_lower_object', 'payload' => ['solicitudServicioRecepcionFactura' => $payloadObject]],
                ['label' => 'wrapper_recepcion_factura_lower_stdclass', 'payload' => $recepcionWrapperLowerObject],
                ['label' => 'soapparam_recepcion_factura', 'payload' => [new SoapParam($payloadObject, 'SolicitudServicioRecepcionFactura')]],
                ['label' => 'soapparam_recepcion_factura_wrapped', 'payload' => [new SoapParam($recepcionSoapVar, 'SolicitudServicioRecepcionFactura')]],
                ['label' => 'soapvar_recepcion_factura', 'payload' => [$recepcionSoapVar]],
                ['label' => 'plain_payload', 'payload' => $payload],
                ['label' => 'plain_object_payload', 'payload' => $payloadObject],
                ['label' => 'wrapper_operation_exact', 'payload' => [$operation => $payload]],
                ['label' => 'wrapper_operation_ucfirst', 'payload' => [ucfirst($operation) => $payload]],
            ],
            'recepcionpaquetefactura' => [
                ['label' => 'wrapper_recepcion_paquete', 'payload' => ['SolicitudServicioRecepcionPaquete' => $payload]],
                ['label' => 'wrapper_recepcion_paquete_object', 'payload' => ['SolicitudServicioRecepcionPaquete' => $payloadObject]],
                ['label' => 'wrapper_recepcion_paquete_stdclass', 'payload' => $recepcionPaqueteWrapperObject],
                ['label' => 'wrapper_recepcion_paquete_lower', 'payload' => ['solicitudServicioRecepcionPaquete' => $payload]],
                ['label' => 'wrapper_recepcion_paquete_lower_object', 'payload' => ['solicitudServicioRecepcionPaquete' => $payloadObject]],
                ['label' => 'wrapper_recepcion_paquete_lower_stdclass', 'payload' => $recepcionPaqueteWrapperLowerObject],
                ['label' => 'soapparam_recepcion_paquete', 'payload' => [new SoapParam($payloadObject, 'SolicitudServicioRecepcionPaquete')]],
                ['label' => 'soapparam_recepcion_paquete_wrapped', 'payload' => [new SoapParam($recepcionPaqueteSoapVar, 'SolicitudServicioRecepcionPaquete')]],
                ['label' => 'soapvar_recepcion_paquete', 'payload' => [$recepcionPaqueteSoapVar]],
                ['label' => 'plain_payload', 'payload' => $payload],
                ['label' => 'plain_object_payload', 'payload' => $payloadObject],
                ['label' => 'wrapper_operation_exact', 'payload' => [$operation => $payload]],
                ['label' => 'wrapper_operation_ucfirst', 'payload' => [ucfirst($operation) => $payload]],
            ],
            'validacionrecepcionpaquetefactura' => [
                ['label' => 'wrapper_validacion_paquete', 'payload' => ['SolicitudServicioValidacionRecepcionPaquete' => $payload]],
                ['label' => 'wrapper_validacion_paquete_object', 'payload' => ['SolicitudServicioValidacionRecepcionPaquete' => $payloadObject]],
                ['label' => 'wrapper_validacion_paquete_stdclass', 'payload' => $validacionPaqueteWrapperObject],
                ['label' => 'wrapper_validacion_paquete_lower', 'payload' => ['solicitudServicioValidacionRecepcionPaquete' => $payload]],
                ['label' => 'wrapper_validacion_paquete_lower_object', 'payload' => ['solicitudServicioValidacionRecepcionPaquete' => $payloadObject]],
                ['label' => 'wrapper_validacion_paquete_lower_stdclass', 'payload' => $validacionPaqueteWrapperLowerObject],
                ['label' => 'soapparam_validacion_paquete', 'payload' => [new SoapParam($payloadObject, 'SolicitudServicioValidacionRecepcionPaquete')]],
                ['label' => 'soapparam_validacion_paquete_wrapped', 'payload' => [new SoapParam($validacionPaqueteSoapVar, 'SolicitudServicioValidacionRecepcionPaquete')]],
                ['label' => 'soapvar_validacion_paquete', 'payload' => [$validacionPaqueteSoapVar]],
                ['label' => 'plain_payload', 'payload' => $payload],
                ['label' => 'plain_object_payload', 'payload' => $payloadObject],
                ['label' => 'wrapper_operation_exact', 'payload' => [$operation => $payload]],
                ['label' => 'wrapper_operation_ucfirst', 'payload' => [ucfirst($operation) => $payload]],
            ],
            'verificacionestadofactura' => [
                ['label' => 'wrapper_verificacion_estado', 'payload' => ['SolicitudServicioVerificacionEstadoFactura' => $payload]],
                ['label' => 'wrapper_verificacion_estado_lower', 'payload' => ['solicitudServicioVerificacionEstadoFactura' => $payload]],
                ['label' => 'soapparam_verificacion_estado', 'payload' => [new SoapParam($payloadObject, 'SolicitudServicioVerificacionEstadoFactura')]],
                ['label' => 'soapvar_verificacion_estado', 'payload' => [$verificacionSoapVar]],
                ['label' => 'plain_payload', 'payload' => $payload],
                ['label' => 'wrapper_operation_exact', 'payload' => [$operation => $payload]],
                ['label' => 'wrapper_operation_ucfirst', 'payload' => [ucfirst($operation) => $payload]],
            ],
            'anulacionfactura' => [
                ['label' => 'wrapper_anulacion_factura', 'payload' => ['SolicitudServicioAnulacionFactura' => $payload]],
                ['label' => 'wrapper_anulacion_factura_lower', 'payload' => ['solicitudServicioAnulacionFactura' => $payload]],
                ['label' => 'soapparam_anulacion_factura', 'payload' => [new SoapParam($payloadObject, 'SolicitudServicioAnulacionFactura')]],
                ['label' => 'soapvar_anulacion_factura', 'payload' => [$anulacionSoapVar]],
                ['label' => 'plain_payload', 'payload' => $payload],
                ['label' => 'wrapper_operation_exact', 'payload' => [$operation => $payload]],
                ['label' => 'wrapper_operation_ucfirst', 'payload' => [ucfirst($operation) => $payload]],
            ],
            'reversionanulacionfactura' => [
                ['label' => 'wrapper_reversion_anulacion_factura', 'payload' => ['SolicitudServicioReversionAnulacionFactura' => $payload]],
                ['label' => 'wrapper_reversion_anulacion_factura_lower', 'payload' => ['solicitudServicioReversionAnulacionFactura' => $payload]],
                ['label' => 'soapparam_reversion_anulacion_factura', 'payload' => [new SoapParam($payloadObject, 'SolicitudServicioReversionAnulacionFactura')]],
                ['label' => 'soapvar_reversion_anulacion_factura', 'payload' => [$reversionAnulacionSoapVar]],
                ['label' => 'plain_payload', 'payload' => $payload],
                ['label' => 'wrapper_operation_exact', 'payload' => [$operation => $payload]],
                ['label' => 'wrapper_operation_ucfirst', 'payload' => [ucfirst($operation) => $payload]],
            ],
            default => [['label' => 'plain_payload', 'payload' => $payload]],
        };
    }

    private function logSoapAttempt(string $operation, array $candidate, array $endpoint, array $response): void
    {
        Log::info('SIAT SOAP attempt', [
            'operation' => $operation,
            'candidate' => $candidate['label'] ?? 'unknown',
            'endpoint_key' => $endpoint['key'] ?? null,
            'endpoint_label' => $endpoint['label'] ?? null,
            'wsdl' => $endpoint['wsdl'] ?? null,
            'success' => (bool) ($response['success'] ?? false),
            'code' => $response['code'] ?? null,
            'message' => $response['message'] ?? null,
            'payload' => $response['payload_debug'] ?? null,
            'raw' => $response['raw'] ?? null,
            'debug' => $response['debug'] ?? null,
        ]);
    }

    private function debugPayload(mixed $payload): mixed
    {
        if ($payload === null || is_scalar($payload)) {
            return $payload;
        }

        if (is_array($payload)) {
            if ($payload === []) {
                return [];
            }

            $debug = [];

            foreach ($payload as $key => $item) {
                if ($key === 'archivo' && is_string($item)) {
                    $debug[$key] = '[binary payload '.strlen($item).' bytes]';
                    continue;
                }

                $debug[$key] = $this->debugPayload($item);
            }

            return $debug;
        }

        if ($payload instanceof SoapParam) {
            return [
                'type' => 'SoapParam',
                'name' => $this->readSoapProperty($payload, 'param_name'),
                'data' => $this->readSoapProperty($payload, 'param_data'),
            ];
        }

        if ($payload instanceof SoapVar) {
            return [
                'type' => 'SoapVar',
                'enc_name' => $this->readSoapProperty($payload, 'enc_name'),
                'enc_ns' => $this->readSoapProperty($payload, 'enc_ns'),
                'enc_value' => $this->readSoapProperty($payload, 'enc_value'),
            ];
        }

        if (is_object($payload)) {
            $debug = [];

            foreach (get_object_vars($payload) as $key => $item) {
                if ($key === 'archivo' && is_string($item)) {
                    $debug[$key] = '[binary payload '.strlen($item).' bytes]';
                    continue;
                }

                $debug[$key] = $this->debugPayload($item);
            }

            return $debug;
        }

        return (string) $payload;
    }

    private function readSoapProperty(object $object, string $property): mixed
    {
        $data = (array) $object;

        foreach ($data as $key => $value) {
            if (str_ends_with((string) $key, $property)) {
                return $this->debugPayload($value);
            }
        }

        return null;
    }

    private function toBooleanValue(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (bool) $value;
        }

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'si', 'yes'], true);
        }

        return false;
    }
}
