<?php

namespace App\Http\Requests\Facturacion;

use App\Helpers\SiatMetodoPagoHelper;
use App\Models\SinMetodoPago;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmitirFacturaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'venta_id' => ['required', 'integer', Rule::exists('venta_cabeceras', 'id')],
            'codigo_metodo_pago' => ['nullable', 'string', Rule::exists('sin_metodos_pago', 'codigo_clasificador')->where(fn ($query) => $query->where('estado', true)->where('habilitado_venta', true))],
            'numero_tarjeta' => ['nullable', 'string', 'max:32'],
            'monto_gift_card' => ['nullable', 'numeric', 'gt:0'],
            'codigo_documento_identidad' => ['nullable', 'string', Rule::exists('sin_documentos_identidad', 'codigo_clasificador')->where('estado', true)],
            'numero_factura_manual' => ['nullable', 'integer', 'min:1'],
            'fecha_emision_manual' => ['nullable', 'date'],
            'observacion' => ['nullable', 'string'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator) {
                $codigoMetodoPago = trim((string) $this->input('codigo_metodo_pago', ''));
                $numeroTarjeta = $this->input('numero_tarjeta');
                $montoGiftCard = $this->input('monto_gift_card');

                if ($codigoMetodoPago === '') {
                    return;
                }

                $metodoPago = SinMetodoPago::query()
                    ->where('estado', true)
                    ->where('codigo_clasificador', $codigoMetodoPago)
                    ->first();

                if (! SiatMetodoPagoHelper::requiresCardNumber($codigoMetodoPago, $metodoPago?->descripcion)) {
                    // continue with other special validations
                } else {
                    if (! filled($numeroTarjeta)) {
                        $validator->errors()->add('numero_tarjeta', 'El numero de tarjeta es obligatorio cuando el metodo de pago es tarjeta.');
                    } elseif (! SiatMetodoPagoHelper::isMaskedCardNumber((string) $numeroTarjeta)) {
                        $validator->errors()->add('numero_tarjeta', 'Registra solo los 4 primeros y 4 ultimos digitos de la tarjeta. El sistema completara el formato SIAT con ceros al medio.');
                    }
                }

                if (SiatMetodoPagoHelper::requiresGiftCardAmount($codigoMetodoPago, $metodoPago?->descripcion)) {
                    if (! filled($montoGiftCard)) {
                        $validator->errors()->add('monto_gift_card', 'El monto gift card es obligatorio cuando el metodo de pago corresponde a gift card.');
                    }
                }
            },
        ];
    }
}
