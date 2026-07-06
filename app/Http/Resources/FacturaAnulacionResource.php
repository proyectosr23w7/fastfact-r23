<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FacturaAnulacionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'factura_id' => $this->factura_id,
            'codigo_motivo_anulacion' => $this->codigo_motivo_anulacion,
            'descripcion_motivo' => $this->descripcion_motivo,
            'fecha_anulacion' => optional($this->fecha_anulacion)?->format('Y-m-d H:i:s'),
            'codigo_respuesta' => $this->codigo_respuesta,
            'descripcion_respuesta' => $this->descripcion_respuesta,
            'datos_respuesta_siat' => $this->datos_respuesta_siat,
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
