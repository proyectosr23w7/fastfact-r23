<?php

namespace App\Models\Configuracion;

use App\Models\ArticuloLote;
use App\Models\Kardex;
use App\Models\VentaCabecera;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sucursal extends Model
{
    protected $table = 'sucursales';

    protected $fillable = [
        'codigo',
        'nombre',
        'direccion',
        'telefono',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'estado' => 'boolean',
        ];
    }

    public function puntosVenta(): HasMany
    {
        return $this->hasMany(PuntoVenta::class);
    }

    public function kardex(): HasMany
    {
        return $this->hasMany(Kardex::class);
    }

    public function lotes(): HasMany
    {
        return $this->hasMany(ArticuloLote::class);
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(VentaCabecera::class);
    }
}
