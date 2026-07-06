<?php

namespace App\Http\Requests\Facturacion;

use App\Helpers\SiatMetodoPagoHelper;
use App\Models\Cliente;
use App\Models\Configuracion\PuntoVenta;
use App\Models\SinMetodoPago;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmitirFacturaDirectaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cliente_id' => ['required', 'integer', Rule::exists('clientes', 'id')],
            'sucursal_id' => ['required', 'integer', Rule::exists('sucursales', 'id')],
            'punto_venta_id' => ['required', 'integer', Rule::exists('puntos_venta', 'id')],
            'codigo_metodo_pago' => ['nullable', 'string', Rule::exists('sin_metodos_pago', 'codigo_clasificador')->where(fn ($query) => $query->where('estado', true)->where('habilitado_venta', true))],
            'numero_tarjeta' => ['nullable', 'string', 'max:32'],
            'monto_gift_card' => ['nullable', 'numeric', 'gt:0'],
            'codigo_documento_identidad' => ['nullable', 'string', Rule::exists('sin_documentos_identidad', 'codigo_clasificador')->where('estado', true)],
            'referencia_externa' => ['nullable', 'string', 'max:120'],
            'origen' => ['nullable', 'string', 'max:40'],
            'observacion' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.articulo_id' => ['nullable', 'integer', Rule::exists('articulos', 'id')],
            'detalles.*.actividad_economica' => ['required', 'string', 'max:20'],
            'detalles.*.codigo_producto_sin' => ['required', 'integer'],
            'detalles.*.codigo_producto' => ['required', 'string', 'max:80'],
            'detalles.*.descripcion' => ['required', 'string', 'max:500'],
            'detalles.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'detalles.*.unidad_medida' => ['required', 'integer'],
            'detalles.*.precio_unitario' => ['required', 'numeric', 'gte:0'],
            'detalles.*.monto_descuento' => ['nullable', 'numeric', 'gte:0'],
            'detalles.*.numero_serie' => ['nullable', 'string', 'max:100'],
            'detalles.*.numero_imei' => ['nullable', 'string', 'max:100'],
            'detalles.*.metadata' => ['nullable', 'array'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator) {
                $cliente = Cliente::query()->find($this->integer('cliente_id'));
                $numeroDocumento = trim((string) ($cliente?->nit_ci ?? ''));

                if ($numeroDocumento === '' || preg_match('/^0+$/', $numeroDocumento) === 1) {
                    $validator->errors()->add('cliente_id', 'El cliente facturable debe tener un numero de documento valido y distinto de 0.');
                }

                if (! filled($cliente?->tipo_documento_identidad)) {
                    $validator->errors()->add('cliente_id', 'El cliente facturable debe tener tipo de documento SIAT.');
                }

                $puntoVentaId = (int) $this->input('punto_venta_id');
                $sucursalId = (int) $this->input('sucursal_id');

                if ($puntoVentaId > 0 && $sucursalId > 0) {
                    $pertenece = PuntoVenta::query()
                        ->whereKey($puntoVentaId)
                        ->where('sucursal_id', $sucursalId)
                        ->exists();

                    if (! $pertenece) {
                        $validator->errors()->add('punto_venta_id', 'El punto de venta no pertenece a la sucursal seleccionada.');
                    }
                }

                $codigoMetodoPago = trim((string) $this->input('codigo_metodo_pago', ''));

                if ($codigoMetodoPago === '') {
                    return;
                }

                $metodoPago = SinMetodoPago::query()
                    ->where('estado', true)
                    ->where('codigo_clasificador', $codigoMetodoPago)
                    ->first();

                if (SiatMetodoPagoHelper::requiresCardNumber($codigoMetodoPago, $metodoPago?->descripcion)) {
                    $numeroTarjeta = $this->input('numero_tarjeta');

                    if (! filled($numeroTarjeta)) {
                        $validator->errors()->add('numero_tarjeta', 'El numero de tarjeta es obligatorio cuando el metodo de pago es tarjeta.');
                    } elseif (! SiatMetodoPagoHelper::isMaskedCardNumber((string) $numeroTarjeta)) {
                        $validator->errors()->add('numero_tarjeta', 'Registra solo los 4 primeros y 4 ultimos digitos de la tarjeta.');
                    }
                }

                if (SiatMetodoPagoHelper::requiresGiftCardAmount($codigoMetodoPago, $metodoPago?->descripcion) && ! filled($this->input('monto_gift_card'))) {
                    $validator->errors()->add('monto_gift_card', 'El monto gift card es obligatorio para este metodo de pago.');
                }
            },
        ];
    }
}
