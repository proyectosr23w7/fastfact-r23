<?php

namespace App\Actions\Facturacion;

use App\Enums\TipoFacturacionEnum;
use App\Helpers\SiatMetodoPagoHelper;
use App\Models\Cliente;
use App\Models\Configuracion\Configuracion;
use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\PuntoVenta;
use App\Models\Configuracion\Sucursal;
use App\Models\SinDocumentoIdentidad;
use App\Models\SinLeyenda;
use App\Models\SinMetodoPago;
use App\Models\SinMoneda;
use App\Models\SinProductoServicio;
use App\Models\SinUnidadMedida;
use App\Models\User;
use Carbon\Carbon;

class GenerarDatosFacturaDirectaAction
{
    public function __invoke(
        Cliente $cliente,
        Sucursal $sucursal,
        PuntoVenta $puntoVenta,
        User $user,
        Configuracion $configuracion,
        Empresa $empresa,
        array $detalles,
        array $extra = [],
    ): array {
        $tipoFacturacion = (int) $configuracion->tipo_facturacion;
        $fechaEmision = $this->resolveFechaEmision($extra['fecha_emision'] ?? null);
        $codigoEmision = (int) ($extra['codigo_emision'] ?? 1);
        $documentoIdentidad = $this->resolveDocumentoIdentidad($cliente, $extra);
        $metodoPago = $this->resolveMetodoPago($extra);
        $detalleFiscal = $this->resolveDetalles($detalles);
        $montoTotal = round(array_sum(array_column($detalleFiscal, 'subTotal')), 2);
        $montoGiftCard = $this->resolveMontoGiftCard($extra, $metodoPago, $montoTotal);
        $montoGiftCardValue = round((float) ($montoGiftCard ?? 0), 2);
        $montoTotalSujetoIva = round(max($montoTotal - $montoGiftCardValue, 0), 2);
        $moneda = $this->resolveMoneda();
        $leyenda = $this->resolveLeyenda($detalleFiscal);

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
                'municipio' => mb_strtoupper((string) ($extra['municipio'] ?? $sucursal->municipio ?? 'LA PAZ')),
                'telefono' => $empresa->telefono ?: null,
                'numeroFactura' => (int) ($extra['numero_factura'] ?? 0),
                'cuf' => null,
                'cufd' => (string) ($extra['cufd'] ?? ''),
                'codigoSucursal' => (int) ($sucursal->codigo ?? 0),
                'direccion' => (string) ($sucursal->direccion ?: $empresa->direccion ?: ''),
                'codigoPuntoVenta' => (int) ($puntoVenta->codigo ?? 0),
                'fechaEmision' => $fechaEmision,
                'nombreRazonSocial' => (string) ($cliente->razon_social ?: $cliente->nombre),
                'codigoTipoDocumentoIdentidad' => $documentoIdentidad,
                'numeroDocumento' => (string) $cliente->nit_ci,
                'complemento' => $cliente->tipo_documento_identidad === '1'
                    ? ($cliente->complemento ?: null)
                    : null,
                'codigoCliente' => (string) ($cliente->codigo ?: $cliente->nit_ci ?: $cliente->id),
                'codigoMetodoPago' => $metodoPago,
                'numeroTarjeta' => $this->resolveNumeroTarjeta($extra, $metodoPago),
                'montoTotal' => $montoTotal,
                'montoTotalSujetoIva' => $montoTotalSujetoIva,
                'codigoMoneda' => $moneda,
                'tipoCambio' => 1,
                'montoTotalMoneda' => $montoTotal,
                'montoGiftCard' => $montoGiftCardValue,
                'descuentoAdicional' => round((float) ($extra['descuento_global'] ?? 0), 2),
                'codigoExcepcion' => $this->resolveCodigoExcepcion($documentoIdentidad, $codigoEmision, $extra),
                'cafc' => filled($extra['cafc'] ?? null) ? trim((string) $extra['cafc']) : null,
                'leyenda' => $leyenda,
                'usuario' => mb_strtoupper((string) ($user->name ?? 'SISTEMA')),
                'codigoDocumentoSector' => 1,
            ],
            'detalle' => $detalleFiscal,
            'referencias' => [
                'origen' => $extra['origen'] ?? 'directa',
                'referencia_externa' => $extra['referencia_externa'] ?? null,
                'cliente_id' => $cliente->id,
                'sucursal_id' => $sucursal->id,
                'punto_venta_id' => $puntoVenta->id,
                'user_id' => $user->id,
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

    private function resolveDocumentoIdentidad(Cliente $cliente, array $extra): string
    {
        $codigo = (string) ($extra['codigo_documento_identidad']
            ?? $cliente->tipo_documento_identidad
            ?? SinDocumentoIdentidad::query()->where('estado', true)->orderBy('codigo_clasificador')->value('codigo_clasificador')
            ?? '1');

        return $codigo === '' ? '1' : $codigo;
    }

    private function resolveMetodoPago(array $extra): string
    {
        return (string) ($extra['codigo_metodo_pago']
            ?? SinMetodoPago::query()
                ->where('estado', true)
                ->where('habilitado_venta', true)
                ->orderByDesc('es_predeterminado')
                ->orderBy('orden_operativo')
                ->orderBy('descripcion')
                ->value('codigo_clasificador')
            ?? '1');
    }

    private function resolveNumeroTarjeta(array $extra, string $codigoMetodoPago): ?string
    {
        $metodoPago = SinMetodoPago::query()
            ->where('estado', true)
            ->where('codigo_clasificador', $codigoMetodoPago)
            ->first();

        if (! SiatMetodoPagoHelper::requiresCardNumber($codigoMetodoPago, $metodoPago?->descripcion)) {
            return null;
        }

        return SiatMetodoPagoHelper::normalizeCardNumber($extra['numero_tarjeta'] ?? null);
    }

    private function resolveMontoGiftCard(array $extra, string $codigoMetodoPago, float $montoTotal): ?float
    {
        $metodoPago = SinMetodoPago::query()
            ->where('estado', true)
            ->where('codigo_clasificador', $codigoMetodoPago)
            ->first();

        if (! SiatMetodoPagoHelper::requiresGiftCardAmount($codigoMetodoPago, $metodoPago?->descripcion)) {
            return null;
        }

        $montoGiftCard = SiatMetodoPagoHelper::normalizeGiftCardAmount($extra['monto_gift_card'] ?? null);

        if ($montoGiftCard === null || $montoGiftCard <= 0) {
            abort(422, 'El monto gift card es obligatorio para este metodo de pago.');
        }

        if ($montoGiftCard > $montoTotal) {
            abort(422, 'El monto gift card no puede ser mayor al total de la factura.');
        }

        return $montoGiftCard;
    }

    private function resolveDetalles(array $detalles): array
    {
        if ($detalles === []) {
            abort(422, 'La factura debe tener al menos un detalle.');
        }

        return collect($detalles)->map(function (array $detalle, int $index): array {
            $descripcion = trim((string) ($detalle['descripcion'] ?? ''));
            $actividad = trim((string) ($detalle['actividad_economica'] ?? ''));
            $codigoProductoSin = trim((string) ($detalle['codigo_producto_sin'] ?? ''));
            $codigoProducto = trim((string) ($detalle['codigo_producto'] ?? ''));
            $unidadMedida = trim((string) ($detalle['unidad_medida'] ?? ''));
            $cantidad = round((float) ($detalle['cantidad'] ?? 0), 5);
            $precioUnitario = round((float) ($detalle['precio_unitario'] ?? 0), 5);
            $descuento = round((float) ($detalle['monto_descuento'] ?? 0), 5);

            if ($descripcion === '' || $actividad === '' || $codigoProductoSin === '' || $codigoProducto === '' || $unidadMedida === '') {
                abort(422, 'El detalle '.($index + 1).' no tiene homologacion SIAT completa.');
            }

            if ($cantidad <= 0 || $precioUnitario < 0 || $descuento < 0) {
                abort(422, 'El detalle '.($index + 1).' tiene importes o cantidades invalidas.');
            }

            $this->validarProductoSin($actividad, $codigoProductoSin, $descripcion);
            $this->validarUnidadMedida($unidadMedida, $descripcion);

            $subtotal = round(($cantidad * $precioUnitario) - $descuento, 5);

            if ($subtotal < 0) {
                abort(422, 'El detalle '.($index + 1).' tiene subtotal negativo.');
            }

            return [
                'articuloId' => filled($detalle['articulo_id'] ?? null) ? (int) $detalle['articulo_id'] : null,
                'actividadEconomica' => $actividad,
                'codigoProductoSin' => (int) $codigoProductoSin,
                'codigoProducto' => $codigoProducto,
                'descripcion' => $descripcion,
                'cantidad' => $cantidad,
                'unidadMedida' => (int) $unidadMedida,
                'precioUnitario' => $precioUnitario,
                'montoDescuento' => $descuento,
                'subTotal' => $subtotal,
                'numeroSerie' => $detalle['numero_serie'] ?? null,
                'numeroImei' => $detalle['numero_imei'] ?? null,
            ];
        })->values()->all();
    }

    private function validarProductoSin(string $actividad, string $codigoProductoSin, string $descripcion): void
    {
        $producto = SinProductoServicio::query()
            ->where('estado', true)
            ->where('codigo_producto', $codigoProductoSin)
            ->first();

        if (! $producto || (string) $producto->codigo_actividad !== $actividad) {
            abort(422, "El servicio '{$descripcion}' tiene un producto SIN no valido para la actividad seleccionada.");
        }
    }

    private function validarUnidadMedida(string $unidadMedida, string $descripcion): void
    {
        $unidad = SinUnidadMedida::query()
            ->where('estado', true)
            ->where('habilitado_uso', true)
            ->where('codigo_clasificador', $unidadMedida)
            ->exists();

        if (! $unidad) {
            abort(422, "El servicio '{$descripcion}' tiene una unidad de medida SIAT no valida o inactiva.");
        }
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

    private function resolveLeyenda(array $detalles): string
    {
        $actividades = collect($detalles)
            ->pluck('actividadEconomica')
            ->map(fn ($actividad) => trim((string) $actividad))
            ->filter()
            ->unique()
            ->values();

        $leyendaPorActividad = $actividades->isNotEmpty()
            ? SinLeyenda::query()
                ->where('estado', true)
                ->whereIn('codigo_actividad', $actividades->all())
                ->inRandomOrder()
                ->value('descripcion_leyenda')
            : null;

        return (string) (
            $leyendaPorActividad
            ?? SinLeyenda::query()->where('estado', true)->inRandomOrder()->value('descripcion_leyenda')
            ?? 'Ley N 453: Tienes derecho a recibir informacion sobre las caracteristicas y contenidos de los servicios que utilices.'
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
}
