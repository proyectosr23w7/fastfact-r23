<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArticuloPrecioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'articulo_id' => $this->articulo_id,
            'cantidad_minima' => (float) $this->cantidad_minima,
            'precio' => (float) $this->precio,
            'tipo_precio' => $this->tipo_precio,
            'estado' => (bool) $this->estado,
            'articulo' => $this->whenLoaded('articulo', fn () => [
                'id' => $this->articulo?->id,
                'nombre' => $this->articulo?->nombre,
                'codigo_generico' => $this->articulo?->codigo_generico,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
