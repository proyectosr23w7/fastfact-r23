<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SinActividad extends Model
{
    protected $table = 'sin_actividades';

    protected $fillable = ['codigo_clasificador', 'descripcion', 'estado'];

    protected function casts(): array
    {
        return ['estado' => 'boolean'];
    }
}
