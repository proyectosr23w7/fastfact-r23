<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Personal extends Model
{
    protected $fillable = [
        'nombre_completo',
        'telefono',
        'numero_documento',
        'fecha_ingreso',
        'puesto_id',
        'activo'
    ];
}
