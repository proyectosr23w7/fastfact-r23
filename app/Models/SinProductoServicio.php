<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SinProductoServicio extends Model
{
    protected $table = 'sin_productos_servicios';

    protected $fillable = ['codigo_actividad', 'codigo_producto', 'descripcion', 'estado'];

    protected function casts(): array
    {
        return ['estado' => 'boolean'];
    }
}
