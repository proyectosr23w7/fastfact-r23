<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VentaCabeceraResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $descuentoItems = $this->relationLoaded('detalle')
            ? round((float) $this->detalle->sum('descuento'), 2)
            : 0.0;

        return [
            'id' => $this->id,
            'numero_venta' => $this->numero_venta,
            'cliente_id' => $this->cliente_id,
            'sucursal_id' => $this->sucursal_id,
            'punto_venta_id' => $this->punto_venta_id,
            'user_id' => $this->user_id,
            'fecha_venta' => optional($this->fecha_venta)?->format('Y-m-d'),
            'subtotal' => (float) $this->subtotal,
            'descuento' => (float) $this->descuento,
            'descuento_global' => (float) $this->descuento,
            'descuento_items' => $descuentoItems,
            'descuento_total' => round((float) $this->descuento + $descuentoItems, 2),
            'impuesto' => (float) ($this->iva ?? $this->impuesto ?? 0),
            'iva' => (float) ($this->iva ?? $this->impuesto ?? 0),
            'it' => (float) ($this->it ?? 0),
            'total' => (float) $this->total,
            'total_costo' => (float) $this->total_costo,
            'utilidad_bruta' => (float) $this->utilidad_bruta,
            'observacion' => $this->observacion,
            'tipo_documento_venta' => is_string($this->tipo_documento_venta) ? $this->tipo_documento_venta : $this->tipo_documento_venta?->value,
            'codigo_metodo_pago' => $this->codigo_metodo_pago,
            'numero_tarjeta' => $this->numero_tarjeta,
            'monto_gift_card' => $this->monto_gift_card !== null ? (float) $this->monto_gift_card : null,
            'requiere_factura' => (bool) $this->requiere_factura,
            'estado' => is_string($this->estado) ? $this->estado : $this->estado?->value,
            'cuf' => $this->cuf,
            'cufd' => $this->cufd,
            'codigo_recepcion' => $this->codigo_recepcion,
            'numero_factura' => $this->numero_factura,
            'codigo_excepcion' => $this->codigo_excepcion,
            'estado_facturacion' => $this->estado_facturacion,
            'pdf_download_url' => $this->canDownloadPdf()
                ? url("/api/ventas/ventas/{$this->id}/descargar/pdf")
                : null,
            'factura' => $this->whenLoaded('factura', fn () => [
                'id' => $this->factura?->id,
                'numero_factura' => $this->factura?->numero_factura,
                'estado_factura' => is_string($this->factura?->estado_factura) ? $this->factura?->estado_factura : $this->factura?->estado_factura?->value,
                'codigo_emision' => (int) ($this->factura?->codigo_emision ?? 1),
                'es_contingencia' => (int) ($this->factura?->codigo_emision ?? 1) === 2,
                'evento_significativo_id' => $this->factura?->evento_significativo_id,
                'xml_download_url' => $this->factura?->id ? url("/api/facturacion/facturas/{$this->factura->id}/descargar/xml") : null,
                'pdf_download_url' => $this->factura?->id ? url("/api/facturacion/facturas/{$this->factura->id}/descargar/pdf") : null,
            ]),
            'cliente' => $this->whenLoaded('cliente', fn () => [
                'id' => $this->cliente?->id,
                'codigo' => $this->cliente?->codigo,
                'nombre' => $this->cliente?->nombre,
                'razon_social' => $this->cliente?->razon_social,
                'nit_ci' => $this->cliente?->nit_ci,
            ]),
            'sucursal' => $this->whenLoaded('sucursal', fn () => [
                'id' => $this->sucursal?->id,
                'codigo' => $this->sucursal?->codigo,
                'nombre' => $this->sucursal?->nombre,
            ]),
            'punto_venta' => $this->whenLoaded('puntoVenta', fn () => [
                'id' => $this->puntoVenta?->id,
                'codigo' => $this->puntoVenta?->codigo,
                'nombre' => $this->puntoVenta?->nombre,
            ]),
            'usuario' => $this->whenLoaded('user', fn () => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'email' => $this->user?->email,
            ]),
            'detalle' => VentaDetalleResource::collection($this->whenLoaded('detalle')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function canDownloadPdf(): bool
    {
        $tipo = is_string($this->tipo_documento_venta) ? $this->tipo_documento_venta : $this->tipo_documento_venta?->value;

        if ($tipo === 'factura') {
            return $this->factura !== null;
        }

        return true;
    }
}
