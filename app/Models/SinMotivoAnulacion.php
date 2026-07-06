<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SinMotivoAnulacion extends Model
{
    protected $table = 'sin_motivos_anulacion';

    protected $fillable = ['codigo_clasificador', 'descripcion', 'estado'];

    protected function casts(): array
    {
        return ['estado' => 'boolean'];
    }
}
