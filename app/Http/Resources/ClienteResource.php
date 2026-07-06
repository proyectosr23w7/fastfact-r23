<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClienteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'razon_social' => $this->razon_social,
            'nit_ci' => $this->nit_ci,
            'tipo_documento_identidad' => $this->tipo_documento_identidad,
            'complemento' => $this->complemento,
            'telefono' => $this->telefono,
            'correo' => $this->correo,
            'direccion' => $this->direccion,
            'estado' => (bool) $this->estado,
        ];
    }
}
