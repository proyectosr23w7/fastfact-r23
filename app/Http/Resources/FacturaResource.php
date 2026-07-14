<?php

namespace App\Http\Resources;

use App\Enums\TipoFacturacionEnum;
use App\Helpers\BoliviaPdfHelper;
use App\Models\Configuracion\Empresa;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FacturaResource extends JsonResource
{
    private static ?string $nitEmisor = null;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cliente_id' => $this->cliente_id,
            'sucursal_id' => $this->sucursal_id,
            'punto_venta_id' => $this->punto_venta_id,
            'user_id' => $this->user_id,
            'cuis_id' => $this->cuis_id,
            'cufd_id' => $this->cufd_id,
            'evento_significativo_id' => $this->evento_significativo_id,
            'cafc_id' => $this->cafc_id,
            'numero_factura' => $this->numero_factura,
            'cuf' => $this->cuf,
            'codigo_recepcion' => $this->codigo_recepcion,
            'codigo_metodo_pago' => $this->codigo_metodo_pago,
            'numero_tarjeta' => $this->numero_tarjeta,
            'monto_gift_card' => $this->monto_gift_card !== null ? (float) $this->monto_gift_card : null,
            'descuento_global' => $this->descuento_global !== null ? (float) $this->descuento_global : null,
            'codigo_documento_identidad' => $this->codigo_documento_identidad,
            'tipo_facturacion' => (int) $this->tipo_facturacion,
            'ambiente_facturacion' => $this->ambiente_facturacion,
            'codigo_emision' => (int) $this->codigo_emision,
            'estado_sincronizacion' => $this->estado_sincronizacion,
            'es_contingencia' => (int) $this->codigo_emision === 2,
            'es_contingencia_manual' => (int) $this->codigo_emision === 2 && $this->cafc_id !== null,
            'xml_download_url' => $this->id ? url("/api/facturacion/facturas/{$this->id}/descargar/xml") : null,
            'pdf_download_url' => $this->id ? url("/api/facturacion/facturas/{$this->id}/descargar/pdf") : null,
            'consulta_siat_url' => $this->consultaSiatUrl(),
            'can_consult' => (int) $this->codigo_emision === 1
                && filled($this->cuf)
                && ! in_array(
                    is_string($this->estado_factura) ? $this->estado_factura : $this->estado_factura?->value,
                    ['pendiente_envio', 'anulada'],
                    true,
                ),
            'can_cancel' => in_array(
                is_string($this->estado_factura) ? $this->estado_factura : $this->estado_factura?->value,
                ['emitida', 'observada'],
                true,
            ) && blank($this->anulacion_revertida_at),
            'can_reverse_cancellation' => (is_string($this->estado_factura) ? $this->estado_factura : $this->estado_factura?->value) === 'anulada'
                && blank($this->anulacion_revertida_at),
            'timeline' => [
                'generada' => [
                    'complete' => filled($this->hash_xml) || filled($this->xml_fiscal),
                    'at' => optional($this->created_at)?->format('Y-m-d H:i:s'),
                ],
                'firmada' => [
                    'complete' => (int) $this->tipo_facturacion !== TipoFacturacionEnum::ELECTRONICA->value || filled($this->hash_xml),
                    'required' => (int) $this->tipo_facturacion === TipoFacturacionEnum::ELECTRONICA->value,
                    'at' => null,
                ],
                'enviada' => [
                    'complete' => (int) $this->codigo_emision === 1
                        && ! in_array(
                            is_string($this->estado_factura) ? $this->estado_factura : $this->estado_factura?->value,
                            ['pendiente', 'pendiente_envio'],
                            true,
                        ),
                    'at' => null,
                ],
                'validada' => [
                    'complete' => in_array(
                        is_string($this->estado_factura) ? $this->estado_factura : $this->estado_factura?->value,
                        ['emitida', 'anulada'],
                        true,
                    ),
                    'status' => is_string($this->estado_factura) ? $this->estado_factura : $this->estado_factura?->value,
                    'at' => null,
                ],
            ],
            'hash_xml' => $this->hash_xml,
            'fecha_emision' => optional($this->fecha_emision)?->format('Y-m-d H:i:s'),
            'monto_total' => (float) $this->monto_total,
            'monto_sujeto_iva' => (float) $this->monto_sujeto_iva,
            'codigo_estado' => $this->codigo_estado,
            'descripcion_estado' => $this->descripcion_estado,
            'estado_factura' => is_string($this->estado_factura) ? $this->estado_factura : $this->estado_factura?->value,
            'can_retry' => false,
            'codigo_excepcion' => $this->codigo_excepcion,
            'observacion' => $this->observacion,
            'datos_respuesta_siat' => $this->datos_respuesta_siat,
            'anulacion_revertida_at' => optional($this->anulacion_revertida_at)?->format('Y-m-d H:i:s'),
            'anulacion_reversion_codigo_respuesta' => $this->anulacion_reversion_codigo_respuesta,
            'anulacion_reversion_descripcion' => $this->anulacion_reversion_descripcion,
            'anulacion_reversion_respuesta_siat' => $this->anulacion_reversion_respuesta_siat,
            'detalles' => $this->whenLoaded('detalles', fn () => $this->detalles->map(fn ($detalle) => [
                'id' => $detalle->id,
                'actividad_economica' => $detalle->actividad_economica,
                'codigo_producto_sin' => $detalle->codigo_producto_sin,
                'codigo_producto' => $detalle->codigo_producto,
                'descripcion' => $detalle->descripcion,
                'cantidad' => (float) $detalle->cantidad,
                'unidad_medida' => $detalle->unidad_medida,
                'precio_unitario' => (float) $detalle->precio_unitario,
                'monto_descuento' => (float) $detalle->monto_descuento,
                'subtotal' => (float) $detalle->subtotal,
                'metadata' => $detalle->metadata,
            ])->values()),
            'cliente' => $this->whenLoaded('cliente', fn () => ClienteResource::make($this->cliente)->resolve()),
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
            'cuis' => $this->whenLoaded('cuis', fn () => CuisResource::make($this->cuis)->resolve()),
            'cufd' => $this->whenLoaded('cufd', fn () => CufdResource::make($this->cufd)->resolve()),
            'cafc' => $this->whenLoaded('cafc', fn () => CafcResource::make($this->cafc)->resolve()),
            'evento_significativo' => $this->whenLoaded('eventoSignificativo', fn () => EventoSignificativoResource::make($this->eventoSignificativo)->resolve()),
            'anulaciones' => $this->whenLoaded('anulaciones', fn () => FacturaAnulacionResource::collection($this->anulaciones)->resolve()),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function consultaSiatUrl(): ?string
    {
        if (blank($this->cuf) || blank($this->numero_factura)) {
            return null;
        }

        $nitEmisor = self::$nitEmisor ??= (string) (Empresa::query()
            ->orderByDesc('estado')
            ->orderBy('id')
            ->value('nit') ?? '');

        if (blank($nitEmisor)) {
            return null;
        }

        return BoliviaPdfHelper::emisorQrUrl($this->resource, $nitEmisor);
    }
}
