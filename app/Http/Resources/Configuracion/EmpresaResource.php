<?php

namespace App\Http\Resources\Configuracion;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class EmpresaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre_empresa' => $this->nombre_empresa,
            'razon_social' => $this->razon_social,
            'nit' => $this->nit,
            'propietario' => $this->propietario,
            'direccion' => $this->direccion,
            'telefono' => $this->telefono,
            'correo' => $this->correo,
            'logo' => $this->logo,
            'logo_url' => $this->logoUrl(),
            'logo_configurado' => $this->hasConfiguredLogo(),
            'estado' => (bool) $this->estado,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function logoUrl(): ?string
    {
        $logo = trim((string) $this->logo);

        if ($logo === '') {
            return null;
        }

        $path = $this->storedLogoPath();

        if ($path !== null && Storage::disk('public')->exists($path)) {
            return route('configuracion.empresa.logo', ['empresa' => $this->id]);
        }

        if (preg_match('/^https?:\/\//i', $logo) || str_starts_with($logo, '/')) {
            return $logo;
        }

        return url($logo);
    }
}
