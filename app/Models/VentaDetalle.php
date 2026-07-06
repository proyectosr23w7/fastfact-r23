<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VentaDetalle extends Model
{
    protected $table = 'venta_detalles';

    protected $fillable = [
        'venta_id',
        'articulo_id',
        'cantidad',
        'precio_unitario',
        'descuento',
        'impuesto',
        'subtotal',
        'total',
        'costo_unitario',
        'costo_total',
        'utilidad_bruta',
        'lote_consumido',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:2',
            'precio_unitario' => 'decimal:2',
            'descuento' => 'decimal:2',
            'impuesto' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
            'costo_unitario' => 'decimal:2',
            'costo_total' => 'decimal:2',
            'utilidad_bruta' => 'decimal:2',
            'lote_consumido' => 'array',
        ];
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(VentaCabecera::class, 'venta_id');
    }

    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class);
    }

    public function lotes(): HasMany
    {
        return $this->hasMany(VentaDetalleLote::class, 'venta_detalle_id');
    }
}
