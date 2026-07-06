<?php

namespace App\Http\Requests\Venta;

use App\Enums\TipoDocumentoVentaEnum;
use App\Helpers\SiatMetodoPagoHelper;
use App\Models\Cliente;
use App\Models\Configuracion\Configuracion;
use App\Models\SinMetodoPago;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rule;

class StoreVentaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cliente_id' => ['required', Rule::exists('clientes', 'id')->where('estado', true)],
            'sucursal_id' => ['required', Rule::exists('sucursales', 'id')->where('estado', true)],
            'punto_venta_id' => ['required', Rule::exists('puntos_venta', 'id')->where('estado', true)],
            'user_id' => ['required', Rule::exists('users', 'id')->where('estado', true)],
            'fecha_venta' => ['required', 'date'],
            'tipo_documento_venta' => ['required', new Enum(TipoDocumentoVentaEnum::class)],
            'descuento_global' => ['nullable', 'numeric', 'gte:0'],
            'codigo_metodo_pago' => ['nullable', 'string', Rule::exists('sin_metodos_pago', 'codigo_clasificador')->where(fn ($query) => $query->where('estado', true)->where('habilitado_venta', true))],
            'numero_tarjeta' => ['nullable', 'string', 'max:32'],
            'monto_gift_card' => ['nullable', 'numeric', 'gt:0'],
            'requiere_factura' => ['required', 'boolean'],
            'observacion' => ['nullable', 'string'],
            'detalle' => ['required', 'array', 'min:1'],
            'detalle.*.articulo_id' => ['required', Rule::exists('articulos', 'id')->where('estado', true)],
            'detalle.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'detalle.*.precio_unitario' => ['required', 'numeric', 'gt:0'],
            'detalle.*.descuento' => ['nullable', 'numeric', 'gte:0'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator) {
                $configuracion = Configuracion::current();
                $facturacionObligatoria = (bool) ($configuracion?->ventasFacturacionObligatoria() ?? false);

                if (! $facturacionObligatoria && (int) $this->input('requiere_factura') === 1 && $this->input('tipo_documento_venta') !== TipoDocumentoVentaEnum::FACTURA->value) {
                    $validator->errors()->add('tipo_documento_venta', 'Si requiere factura, el tipo de documento debe ser factura.');
                }

                $puntoVentaId = (int) $this->input('punto_venta_id');
                $sucursalId = (int) $this->input('sucursal_id');

                if ($puntoVentaId > 0 && $sucursalId > 0) {
                    $pertenece = \App\Models\Configuracion\PuntoVenta::query()
                        ->whereKey($puntoVentaId)
                        ->where('sucursal_id', $sucursalId)
                        ->exists();

                    if (! $pertenece) {
                        $validator->errors()->add('punto_venta_id', 'El punto de venta no pertenece a la sucursal seleccionada.');
                    }
                }

                $descuentoGlobal = (float) ($this->input('descuento_global') ?? 0);
                $detalle = collect($this->input('detalle', []));
                $subtotal = $detalle->sum(function ($item) {
                    return (float) ($item['cantidad'] ?? 0) * (float) ($item['precio_unitario'] ?? 0);
                });
                $descuentoItems = $detalle->sum(fn ($item) => (float) ($item['descuento'] ?? 0));

                $detalle->each(function ($item, $index) use ($validator) {
                    $cantidad = (float) ($item['cantidad'] ?? 0);
                    $precioUnitario = (float) ($item['precio_unitario'] ?? 0);
                    $descuentoLinea = round((float) ($item['descuento'] ?? 0), 2);
                    $subtotalLinea = round($cantidad * $precioUnitario, 2);

                    if ($descuentoLinea > $subtotalLinea) {
                        $validator->errors()->add(
                            "detalle.{$index}.descuento",
                            'El descuento lineal no puede ser mayor al subtotal de la linea.'
                        );
                    }
                });

                if ($descuentoGlobal > round(max($subtotal - $descuentoItems, 0), 2)) {
                    $validator->errors()->add('descuento_global', 'El descuento global no puede ser mayor al subtotal neto luego de descuentos por item.');
                }

                $requiereFactura = $facturacionObligatoria
                    || $this->boolean('requiere_factura')
                    || $this->input('tipo_documento_venta') === TipoDocumentoVentaEnum::FACTURA->value;

                if ($requiereFactura) {
                    $cliente = Cliente::query()->find($this->input('cliente_id'));
                    $numeroDocumento = trim((string) ($cliente?->nit_ci ?? ''));
                    $totalFacturable = round(max($subtotal - $descuentoItems - $descuentoGlobal, 0), 2);

                    if (! $cliente) {
                        $validator->errors()->add('cliente_id', 'Debe seleccionar un cliente valido para registrar una venta facturable.');
                    } else {
                        if ($numeroDocumento === '' || preg_match('/^0+$/', $numeroDocumento) === 1) {
                            $validator->errors()->add('cliente_id', 'El cliente facturable debe tener un numero de documento valido y distinto de 0.');
                        }

                        if (! filled($cliente->razon_social ?: $cliente->nombre)) {
                            $validator->errors()->add('cliente_id', 'El cliente facturable debe tener razon social registrada.');
                        }

                        if (! filled($cliente->tipo_documento_identidad)) {
                            $validator->errors()->add('cliente_id', 'El cliente facturable debe tener tipo de documento SIAT.');
                        }
                    }

                    if ($totalFacturable <= 0) {
                        $validator->errors()->add(
                            'detalle',
                            'La venta facturable debe conservar un total mayor a 0 despues de aplicar descuentos lineales y globales.'
                        );
                    }
                }

                $codigoMetodoPago = trim((string) $this->input('codigo_metodo_pago', ''));
                $numeroTarjeta = $this->input('numero_tarjeta');

                if ($codigoMetodoPago !== '') {
                    $metodoPago = SinMetodoPago::query()
                        ->where('estado', true)
                        ->where('habilitado_venta', true)
                        ->where('codigo_clasificador', $codigoMetodoPago)
                        ->first();

                    if (SiatMetodoPagoHelper::requiresCardNumber($codigoMetodoPago, $metodoPago?->descripcion)) {
                        if (! filled($numeroTarjeta)) {
                            $validator->errors()->add('numero_tarjeta', 'El numero de tarjeta es obligatorio cuando el metodo de pago es tarjeta.');
                        } elseif (! SiatMetodoPagoHelper::isMaskedCardNumber((string) $numeroTarjeta)) {
                            $validator->errors()->add('numero_tarjeta', 'Registra solo los 4 primeros y 4 ultimos digitos de la tarjeta. El sistema completara el formato SIAT con ceros al medio.');
                        }
                    }

                    if (SiatMetodoPagoHelper::requiresGiftCardAmount($codigoMetodoPago, $metodoPago?->descripcion)) {
                        $montoGiftCard = $this->input('monto_gift_card');
                        $descuentoGlobal = (float) ($this->input('descuento_global') ?? 0);
                        $totalCalculado = collect($this->input('detalle', []))->sum(function ($item) {
                            $cantidad = (float) ($item['cantidad'] ?? 0);
                            $precio = (float) ($item['precio_unitario'] ?? 0);
                            $descuento = (float) ($item['descuento'] ?? 0);

                            return ($cantidad * $precio) - $descuento;
                        });
                        $totalCalculado = round(max($totalCalculado - $descuentoGlobal, 0), 2);

                        if (! filled($montoGiftCard)) {
                            $validator->errors()->add('monto_gift_card', 'El monto gift card es obligatorio cuando el metodo de pago corresponde a gift card.');
                        } elseif ((float) $montoGiftCard > round($totalCalculado, 2)) {
                            $validator->errors()->add('monto_gift_card', 'El monto gift card no puede ser mayor al total de la venta.');
                        }
                    }
                }
            },
        ];
    }
}
