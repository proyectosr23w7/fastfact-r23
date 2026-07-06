<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sucursal extends Model
{
    protected $table = 'sucursales';

    protected $fillable = [
        'codigo_sucursal',
        'nombre',
        'es_global',
        'activo'
    ];
}
