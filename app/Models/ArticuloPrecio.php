<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticuloPrecio extends Model
{
    protected $table = 'articulo_precios';

    protected $fillable = [
        'articulo_id',
        'cantidad_minima',
        'precio',
        'tipo_precio',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'cantidad_minima' => 'decimal:2',
            'precio' => 'decimal:2',
            'estado' => 'boolean',
        ];
    }

    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class);
    }
}
