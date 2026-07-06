<?php

namespace App\Models;

use App\Models\Configuracion\Sucursal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticuloStock extends Model
{
    protected $table = 'articulo_stocks';

    protected $fillable = [
        'articulo_id',
        'sucursal_id',
        'stock_actual',
    ];

    protected function casts(): array
    {
        return [
            'stock_actual' => 'decimal:2',
        ];
    }

    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class);
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }
}
