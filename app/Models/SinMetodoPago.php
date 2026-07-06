<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SinMetodoPago extends Model
{
    protected $table = 'sin_metodos_pago';

    protected $fillable = ['codigo_clasificador', 'descripcion', 'estado', 'habilitado_venta', 'es_predeterminado', 'orden_operativo'];

    protected function casts(): array
    {
        return [
            'estado' => 'boolean',
            'habilitado_venta' => 'boolean',
            'es_predeterminado' => 'boolean',
            'orden_operativo' => 'integer',
        ];
    }
}
