<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Empresa extends Model
{
    protected $table = 'empresas';

    protected $fillable = [
        'nombre_empresa',
        'razon_social',
        'nit',
        'propietario',
        'direccion',
        'telefono',
        'correo',
        'logo',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'estado' => 'boolean',
        ];
    }

    public function storedLogoPath(): ?string
    {
        $logo = trim((string) $this->logo);

        if ($logo === '' || preg_match('/^https?:\/\//i', $logo)) {
            return null;
        }

        $path = ltrim($logo, '/');

        return str_starts_with($path, 'storage/')
            ? substr($path, strlen('storage/'))
            : $path;
    }

    public function hasConfiguredLogo(): bool
    {
        $logo = trim((string) $this->logo);

        if ($logo === '') {
            return false;
        }

        if (preg_match('/^https?:\/\//i', $logo)) {
            return true;
        }

        $path = $this->storedLogoPath();

        return $path !== null && Storage::disk('public')->exists($path);
    }
}
