<?php

namespace App\Http\Resources\Configuracion;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PersonalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre_completo' => $this->nombre_completo,
            'telefono' => $this->telefono,
            'numero_documento' => $this->numero_documento,
            'fecha_ingreso' => $this->fecha_ingreso,
            'puesto_id' => $this->puesto_id,
            'activo' => (bool) $this->activo,
            'puesto' => $this->whenLoaded('puesto', fn () => [
                'id' => $this->puesto?->id,
                'nombre' => $this->puesto?->nombre,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
