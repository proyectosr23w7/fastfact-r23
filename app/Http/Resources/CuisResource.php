<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CuisResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'sucursal_id' => $this->sucursal_id,
            'punto_venta_id' => $this->punto_venta_id,
            'ambiente_facturacion' => $this->ambiente_facturacion,
            'fecha_vigencia' => optional($this->fecha_vigencia)?->format('Y-m-d H:i:s'),
            'estado' => (bool) $this->estado,
            'codigo_respuesta' => $this->codigo_respuesta,
            'descripcion_respuesta' => $this->descripcion_respuesta,
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
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
