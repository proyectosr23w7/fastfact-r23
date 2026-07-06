<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArticuloLoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'articulo_id' => $this->articulo_id,
            'sucursal_id' => $this->sucursal_id,
            'lote' => $this->lote,
            'fecha_vencimiento' => optional($this->fecha_vencimiento)?->format('Y-m-d'),
            'cantidad_inicial' => (float) $this->cantidad_inicial,
            'cantidad_actual' => (float) $this->cantidad_actual,
            'costo_unitario' => (float) $this->costo_unitario,
            'estado' => (bool) $this->estado,
        ];
    }
}
