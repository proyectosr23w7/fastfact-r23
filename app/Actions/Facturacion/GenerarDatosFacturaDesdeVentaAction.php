<?php

namespace App\Actions\Facturacion;

use App\Enums\TipoFacturacionEnum;
use App\Helpers\SiatMetodoPagoHelper;
use App\Models\Articulo;
use App\Models\Configuracion\Configuracion;
use App\Models\Configuracion\Empresa;
use App\Models\SinDocumentoIdentidad;
use App\Models\SinLeyenda;
use App\Models\SinMetodoPago;
use App\Models\SinMoneda;
use App\Models\SinProductoServicio;
use App\Models\SinUnidadMedida;
use App\Models\VentaCabecera;
use Carbon\Carbon;

class GenerarDatosFacturaDesdeVentaAction
{
    public function __invoke(VentaCabecera $venta, Configuracion $configuracion, Empresa $empresa, array $extra = []): array
    {
        $tipoFacturacion = (int) $configuracion->tipo_facturacion;
        $fechaEmision = $this->resolveFechaEmision($extra['fecha_emision'] ?? null);

        $documentoIdentidad = $this->resolveDocumentoIdentidad($venta, $extra);
        $metodoPago = $this->resolveMetodoPago($venta, $extra);
        $numeroTarjeta = $this->resolveNumeroTarjeta($venta, $extra, $metodoPago);
        $montoGiftCard = $this->resolveMontoGiftCard($venta, $extra, $metodoPago);
        $codigoEmision = (int) ($extra['codigo_emision'] ?? 1);
        $montoGiftCardValue = round((float) ($montoGiftCard ?? 0), 2);
        $montoTotal = round((float) $venta->total, 2);
        $montoTotalSujetoIva = round(max($montoTotal - $montoGiftCardValue, 0), 2);
        $moneda = $this->resolveMoneda();
        $leyenda = $this->resolveLeyenda();

        $detalles = $venta->detalle->map(function ($detalle) {
            $producto = $this->resolveProductoSiat($detalle->articulo);
            $unidad = $this->resolveUnidadMedidaSiat($detalle->articulo);
            $subtotal = round((float) $detalle->total, 2);

            return [
                'actividadEconomica' => $producto['codigo_actividad'],
                'codigoProductoSin' => $producto['codigo_producto'],
                'codigoProducto' => (string) ($detalle->articulo->codigo_generico ?: 'ART-'.$detalle->articulo_id),
                'descripcion' => (string) $detalle->articulo->nombre,
                'cantidad' => round((float) $detalle->cantidad, 2),
                'unidadMedida' => $unidad,
                'precioUnitario' => round((float) $detalle->precio_unitario, 2),
                'montoDescuento' => round((float) $detalle->descuento, 2),
                'subTotal' => $subtotal,
                'numeroSerie' => null,
                'numeroImei' => null,
            ];
        })->values()->all();

        return [
            'root' => $tipoFacturacion === TipoFacturacionEnum::COMPUTARIZADA->value
                ? 'facturaComputarizadaCompraVenta'
                : 'facturaElectronicaCompraVenta',
            'schema_location' => $tipoFacturacion === TipoFacturacionEnum::COMPUTARIZADA->value
                ? 'facturaComputarizadaCompraVenta.xsd'
                : 'facturaElectronicaCompraVenta.xsd',
            'cabecera' => [
                'nitEmisor' => preg_replace('/\D+/', '', (string) $empresa->nit) ?: '',
                'razonSocialEmisor' => (string) ($empresa->razon_social ?: $empresa->nombre_empresa),
                'municipio' => mb_strtoupper((string) ($extra['municipio'] ?? $venta->sucursal?->nombre ?? 'LA PAZ')),
                'telefono' => $empresa->telefono ?: null,
                'numeroFactura' => (int) ($extra['numero_factura'] ?? 0),
                'cuf' => null,
                'cufd' => (string) ($extra['cufd'] ?? ''),
                'codigoSucursal' => (int) ($venta->sucursal?->codigo ?? 0),
                'direccion' => (string) ($venta->sucursal?->direccion ?: $empresa->direccion ?: ''),
                'codigoPuntoVenta' => (int) ($venta->puntoVenta?->codigo ?? 0),
                'fechaEmision' => $fechaEmision,
                'nombreRazonSocial' => (string) ($venta->cliente->razon_social ?: $venta->cliente->nombre),
                'codigoTipoDocumentoIdentidad' => $documentoIdentidad,
                'numeroDocumento' => (string) $venta->cliente->nit_ci,
                'complemento' => $venta->cliente->tipo_documento_identidad === '1'
                    ? ($venta->cliente->complemento ?: null)
                    : null,
                'codigoCliente' => (string) ($venta->cliente->codigo ?: $venta->cliente->nit_ci ?: $venta->cliente_id),
                'codigoMetodoPago' => $metodoPago,
                'numeroTarjeta' => $numeroTarjeta,
                'montoTotal' => $montoTotal,
                'montoTotalSujetoIva' => $montoTotalSujetoIva,
                'codigoMoneda' => $moneda,
                'tipoCambio' => 1,
                'montoTotalMoneda' => $montoTotal,
                'montoGiftCard' => $montoGiftCardValue,
                'descuentoAdicional' => round((float) $venta->descuento, 2),
                'codigoExcepcion' => $this->resolveCodigoExcepcion($documentoIdentidad, $codigoEmision, $extra),
                'cafc' => filled($extra['cafc'] ?? null) ? trim((string) $extra['cafc']) : null,
                'leyenda' => $leyenda,
                'usuario' => mb_strtoupper((string) ($venta->user?->name ?? 'SISTEMA')),
                'codigoDocumentoSector' => 1,
            ],
            'detalle' => $detalles,
            'referencias' => [
                'venta_id' => $venta->id,
                'numero_venta' => $venta->numero_venta,
                'cliente_id' => $venta->cliente_id,
                'sucursal_id' => $venta->sucursal_id,
                'punto_venta_id' => $venta->punto_venta_id,
                'user_id' => $venta->user_id,
                'tipo_facturacion' => $tipoFacturacion,
                'codigo_emision' => $codigoEmision,
                'evento_significativo_id' => $extra['evento_significativo_id'] ?? null,
            ],
        ];
    }

    private function resolveFechaEmision(mixed $fechaEmision): Carbon
    {
        if ($fechaEmision instanceof Carbon) {
            return $fechaEmision->copy()->timezone(config('app.timezone'));
        }

        if (is_string($fechaEmision) && trim($fechaEmision) !== '') {
            return Carbon::parse($fechaEmision, config('app.timezone'))->timezone(config('app.timezone'));
        }

        return now(config('app.timezone'));
    }

    private function resolveDocumentoIdentidad(VentaCabecera $venta, array $extra): string
    {
        $codigo = (string) ($extra['codigo_documento_identidad']
            ?? $venta->cliente->tipo_documento_identidad
            ?? SinDocumentoIdentidad::query()->where('estado', true)->orderBy('codigo_clasificador')->value('codigo_clasificador')
            ?? '1');

        return $codigo === '' ? '1' : $codigo;
    }

    private function resolveMetodoPago(VentaCabecera $venta, array $extra): string
    {
        return (string) ($extra['codigo_metodo_pago']
            ?? $venta->codigo_metodo_pago
            ?? SinMetodoPago::query()
                ->where('estado', true)
                ->where('habilitado_venta', true)
                ->orderByDesc('es_predeterminado')
                ->orderBy('orden_operativo')
                ->orderBy('descripcion')
                ->value('codigo_clasificador')
            ?? '1');
    }

    private function resolveNumeroTarjeta(VentaCabecera $venta, array $extra, string $codigoMetodoPago): ?string
    {
        $metodoPago = SinMetodoPago::query()
            ->where('estado', true)
            ->where('codigo_clasificador', $codigoMetodoPago)
            ->first();

        if (! SiatMetodoPagoHelper::requiresCardNumber($codigoMetodoPago, $metodoPago?->descripcion)) {
            return null;
        }

        return SiatMetodoPagoHelper::normalizeCardNumber(
            $extra['numero_tarjeta'] ?? $venta->numero_tarjeta,
        );
    }

    private function resolveMontoGiftCard(VentaCabecera $venta, array $extra, string $codigoMetodoPago): ?float
    {
        $metodoPago = SinMetodoPago::query()
            ->where('estado', true)
            ->where('codigo_clasificador', $codigoMetodoPago)
            ->first();

        if (! SiatMetodoPagoHelper::requiresGiftCardAmount($codigoMetodoPago, $metodoPago?->descripcion)) {
            return null;
        }

        $amount = SiatMetodoPagoHelper::normalizeGiftCardAmount(
            $extra['monto_gift_card'] ?? $venta->monto_gift_card,
        );

        if ($amount === null) {
            return null;
        }

        return min($amount, round((float) $venta->total, 2));
    }

    private function resolveMoneda(): string
    {
        return (string) (
            SinMoneda::query()
                ->where('estado', true)
                ->where(function ($query) {
                    $query->where('codigo_clasificador', '1')
                        ->orWhere('descripcion', 'like', '%boliv%');
                })
                ->orderBy('codigo_clasificador')
                ->value('codigo_clasificador')
            ?? '1'
        );
    }

    private function resolveLeyenda(): string
    {
        return (string) (
            SinLeyenda::query()->where('estado', true)->inRandomOrder()->value('descripcion_leyenda')
            ?? 'Ley N° 453: Tienes derecho a recibir información sobre las características y contenidos de los servicios que utilices.'
        );
    }

    private function resolveCodigoExcepcion(string $codigoDocumentoIdentidad, int $codigoEmision, array $extra): int
    {
        if (array_key_exists('codigo_excepcion', $extra) && $extra['codigo_excepcion'] !== null) {
            return (int) $extra['codigo_excepcion'];
        }

        if ($codigoEmision !== 2) {
            return 0;
        }

        $documento = SinDocumentoIdentidad::query()
            ->where('estado', true)
            ->where('codigo_clasificador', $codigoDocumentoIdentidad)
            ->first();

        return str_contains(mb_strtoupper((string) $documento?->descripcion), 'NIT') ? 1 : 0;
    }

    private function resolveProductoSiat(Articulo $articulo): array
    {
        $codigoActividad = trim((string) $articulo->codigo_actividad_economica);
        $codigoProducto = trim((string) $articulo->codigo_producto_sin);

        if ($codigoActividad === '' || $codigoProducto === '') {
            abort(422, "El articulo '{$articulo->nombre}' no tiene homologacion SIAT completa. Debes definir actividad economica y producto SIN en la ficha del articulo.");
        }

        $producto = SinProductoServicio::query()
            ->where('estado', true)
            ->where('codigo_producto', $codigoProducto)
            ->first();

        if (! $producto) {
            abort(422, "El articulo '{$articulo->nombre}' tiene un producto SIN no valido o inactivo.");
        }

        if ((string) $producto->codigo_actividad !== $codigoActividad) {
            abort(422, "El articulo '{$articulo->nombre}' tiene una homologacion SIAT inconsistente: el producto SIN no pertenece a la actividad economica seleccionada.");
        }

        return [
            'codigo_actividad' => $codigoActividad,
            'codigo_producto' => $codigoProducto,
        ];
    }

    private function resolveUnidadMedidaSiat(Articulo $articulo): string
    {
        $codigoUnidad = trim((string) $articulo->codigo_unidad_medida_siat);

        if ($codigoUnidad === '') {
            abort(422, "El articulo '{$articulo->nombre}' no tiene homologada la unidad de medida SIAT.");
        }

        $unidad = SinUnidadMedida::query()
            ->where('estado', true)
            ->where('habilitado_uso', true)
            ->where('codigo_clasificador', $codigoUnidad)
            ->value('codigo_clasificador');

        if (! $unidad) {
            abort(422, "El articulo '{$articulo->nombre}' tiene una unidad de medida SIAT no valida o inactiva.");
        }

        return (string) $unidad;
    }
}
