<?php

namespace App\Http\Resources\Configuracion;

use App\Http\Resources\CuisResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PuntoVentaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sucursal_id' => $this->sucursal_id,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'tipo_impresion' => $this->tipo_impresion,
            'estado' => (bool) $this->estado,
            'cuis_vigente' => $this->whenLoaded('cuisVigente', fn () => $this->cuisVigente
                ? CuisResource::make($this->cuisVigente)->resolve()
                : null),
            'sucursal' => $this->whenLoaded('sucursal', fn () => [
                'id' => $this->sucursal?->id,
                'codigo' => $this->sucursal?->codigo,
                'nombre' => $this->sucursal?->nombre,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
