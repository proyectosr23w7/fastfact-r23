<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SinLeyenda extends Model
{
    protected $table = 'sin_leyendas';

    protected $fillable = ['codigo_actividad', 'descripcion_leyenda', 'estado'];

    protected function casts(): array
    {
        return ['estado' => 'boolean'];
    }
}
