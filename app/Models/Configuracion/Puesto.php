<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Puesto extends Model
{
    protected $table = 'puestos';

    protected $fillable = [
        'nombre',
        'descripcion',
    ];

    public function personal(): HasMany
    {
        return $this->hasMany(Personal::class);
    }
}
