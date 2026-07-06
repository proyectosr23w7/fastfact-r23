<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SinUnidadMedida extends Model
{
    protected $table = 'sin_unidades_medida';

    protected $fillable = ['codigo_clasificador', 'descripcion', 'estado', 'habilitado_uso'];

    protected function casts(): array
    {
        return [
            'estado' => 'boolean',
            'habilitado_uso' => 'boolean',
        ];
    }
}
