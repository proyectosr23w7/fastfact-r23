<?php

namespace App\Models;

use App\Models\Configuracion\Sucursal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticuloLote extends Model
{
    protected $table = 'articulo_lotes';

    protected $fillable = [
        'articulo_id',
        'sucursal_id',
        'lote',
        'fecha_vencimiento',
        'cantidad_inicial',
        'cantidad_actual',
        'costo_unitario',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'fecha_vencimiento' => 'date',
            'cantidad_inicial' => 'decimal:2',
            'cantidad_actual' => 'decimal:2',
            'costo_unitario' => 'decimal:2',
            'estado' => 'boolean',
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
