<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VentaDetalleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'venta_id' => $this->venta_id,
            'articulo_id' => $this->articulo_id,
            'cantidad' => (float) $this->cantidad,
            'precio_unitario' => (float) $this->precio_unitario,
            'descuento' => (float) $this->descuento,
            'descuento_unitario' => (float) $this->descuento,
            'impuesto' => 0.0,
            'subtotal' => (float) $this->subtotal,
            'total' => (float) $this->total,
            'costo_unitario' => (float) $this->costo_unitario,
            'costo_total' => (float) $this->costo_total,
            'utilidad_bruta' => (float) $this->utilidad_bruta,
            'lote_consumido' => $this->lote_consumido,
            'articulo' => $this->whenLoaded('articulo', fn () => [
                'id' => $this->articulo?->id,
                'nombre' => $this->articulo?->nombre,
                'codigo_generico' => $this->articulo?->codigo_generico,
                'precio_base' => (float) ($this->articulo?->precio_base ?? 0),
                'costo' => (float) ($this->articulo?->costo ?? 0),
                'stock_actual' => (float) ($this->articulo?->stock_actual ?? 0),
            ]),
            'lotes' => VentaDetalleLoteResource::collection($this->whenLoaded('lotes')),
        ];
    }
}
