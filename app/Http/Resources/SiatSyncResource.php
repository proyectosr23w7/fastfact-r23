<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SiatSyncResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo_sucursal' => (int) $this->codigo_sucursal,
            'codigo_punto_venta' => (int) $this->codigo_punto_venta,
            'tipo_catalogo' => $this->tipo_catalogo,
            'fecha_sincronizacion' => optional($this->fecha_sincronizacion)?->format('Y-m-d H:i:s'),
            'fecha_sincronizacion_iso' => optional($this->fecha_sincronizacion)?->toIso8601String(),
            'estado' => $this->estado,
            'observacion' => $this->observacion,
            'duracion_segundos' => null,
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
