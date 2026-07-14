<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    protected $table = 'clientes';

    protected $fillable = [
        'codigo',
        'nombre',
        'razon_social',
        'nit_ci',
        'tipo_documento_identidad',
        'complemento',
        'telefono',
        'correo',
        'direccion',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'estado' => 'boolean',
        ];
    }

    public function facturas(): HasMany
    {
        return $this->hasMany(Factura::class, 'cliente_id');
    }
}
