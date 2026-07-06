<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SinMoneda extends Model
{
    protected $table = 'sin_monedas';

    protected $fillable = ['codigo_clasificador', 'descripcion', 'estado'];

    protected function casts(): array
    {
        return ['estado' => 'boolean'];
    }
}
