<?php

namespace App\Http\Resources\Configuracion;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SucursalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'codigo' => (int) $this->codigo,
            'nombre' => $this->nombre,
            'municipio' => $this->municipio,
            'direccion' => $this->direccion,
            'telefono' => $this->telefono,
            'estado' => (bool) $this->estado,
            'puntos_venta_count' => $this->whenCounted('puntosVenta', fn () => (int) $this->puntos_venta_count),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
