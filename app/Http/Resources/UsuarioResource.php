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
