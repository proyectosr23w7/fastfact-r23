<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UsuarioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'sucursal_id' => $this->sucursal_id,
            'punto_venta_id' => $this->punto_venta_id,
            'sucursal' => $this->whenLoaded('sucursal', fn () => $this->sucursal ? [
                'id' => (int) $this->sucursal->id,
                'codigo' => (int) $this->sucursal->codigo,
                'nombre' => $this->sucursal->nombre,
            ] : null),
            'punto_venta' => $this->whenLoaded('puntoVenta', fn () => $this->puntoVenta ? [
                'id' => (int) $this->puntoVenta->id,
                'sucursal_id' => (int) $this->puntoVenta->sucursal_id,
                'codigo' => (int) $this->puntoVenta->codigo,
                'nombre' => $this->puntoVenta->nombre,
            ] : null),
            'estado' => (bool) $this->estado,
            'roles' => RolResource::collection($this->whenLoaded('roles')),
            'permission_slugs' => $this->when(
                method_exists($this->resource, 'getPermissionSlugsAttribute'),
                fn () => $this->permission_slugs,
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
