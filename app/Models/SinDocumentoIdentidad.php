<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SinDocumentoIdentidad extends Model
{
    protected $table = 'sin_documentos_identidad';

    protected $fillable = ['codigo_clasificador', 'descripcion', 'estado'];

    protected function casts(): array
    {
        return ['estado' => 'boolean'];
    }
}
