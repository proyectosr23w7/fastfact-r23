<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KardexResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'articulo_id' => $this->articulo_id,
            'sucursal_id' => $this->sucursal_id,
            'tipo_movimiento' => $this->tipo_movimiento,
            'referencia_tipo' => $this->referencia_tipo,
            'referencia_id' => $this->referencia_id,
            'fecha' => optional($this->fecha)?->format('Y-m-d H:i:s'),
            'entrada' => (float) $this->entrada,
            'salida' => (float) $this->salida,
            'saldo' => (float) $this->saldo,
            'costo_unitario' => (float) $this->costo_unitario,
            'costo_total' => (float) $this->costo_total,
            'stock_anterior' => (float) $this->stock_anterior,
            'stock_nuevo' => (float) $this->stock_nuevo,
            'observacion' => $this->observacion,
            'articulo' => $this->whenLoaded('articulo', fn () => [
                'id' => $this->articulo?->id,
                'nombre' => $this->articulo?->nombre,
                'codigo_generico' => $this->articulo?->codigo_generico,
            ]),
            'sucursal' => $this->whenLoaded('sucursal', fn () => [
                'id' => $this->sucursal?->id,
                'codigo' => $this->sucursal?->codigo,
                'nombre' => $this->sucursal?->nombre,
            ]),
            'usuario' => $this->whenLoaded('user', fn () => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
            ]),
        ];
    }
}
