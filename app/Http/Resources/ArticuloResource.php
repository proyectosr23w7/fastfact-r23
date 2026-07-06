<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArticuloResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo_generico' => $this->codigo_generico,
            'codigo_barras' => $this->codigo_barras,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'tags' => $this->tags ?? [],
            'alias' => $this->alias ?? [],
            'atributos' => $this->atributos ?? [],
            'categoria_id' => $this->categoria_id,
            'marca_id' => $this->marca_id,
            'unidad_medida_id' => $this->unidad_medida_id,
            'codigo_actividad_economica' => $this->codigo_actividad_economica,
            'codigo_producto_sin' => $this->codigo_producto_sin,
            'codigo_unidad_medida_siat' => $this->codigo_unidad_medida_siat,
            'homologacion_siat_completa' => filled($this->codigo_actividad_economica) && filled($this->codigo_producto_sin) && filled($this->codigo_unidad_medida_siat),
            'stock_minimo' => (float) $this->stock_minimo,
            'stock_actual' => (float) $this->stock_actual,
            'costo' => (float) $this->costo,
            'precio_base' => (float) $this->precio_base,
            'estado' => (bool) $this->estado,
            'categoria' => $this->whenLoaded('categoria', fn () => [
                'id' => $this->categoria?->id,
                'nombre' => $this->categoria?->nombre,
            ]),
            'marca' => $this->whenLoaded('marca', fn () => $this->marca ? [
                'id' => $this->marca->id,
                'nombre' => $this->marca->nombre,
            ] : null),
            'unidad_medida' => $this->whenLoaded('unidadMedida', fn () => [
                'id' => $this->unidadMedida?->id,
                'nombre' => $this->unidadMedida?->nombre,
                'abreviatura' => $this->unidadMedida?->abreviatura,
            ]),
            'precios' => ArticuloPrecioResource::collection($this->whenLoaded('precios')),
            'stocks' => [],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
