<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VentaDetalleLoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'venta_detalle_id' => $this->venta_detalle_id,
            'articulo_id' => $this->articulo_id,
            'articulo_lote_id' => $this->articulo_lote_id,
            'lote' => $this->lote,
            'cantidad' => (float) $this->cantidad,
            'costo_unitario' => (float) $this->costo_unitario,
            'costo_total' => (float) $this->costo_total,
            'fecha_vencimiento' => optional($this->fecha_vencimiento)?->format('Y-m-d'),
        ];
    }
}
