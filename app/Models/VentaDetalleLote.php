<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VentaDetalleLote extends Model
{
    protected $table = 'venta_detalle_lotes';

    protected $fillable = [
        'venta_detalle_id',
        'articulo_id',
        'articulo_lote_id',
        'lote',
        'cantidad',
        'costo_unitario',
        'costo_total',
        'fecha_vencimiento',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:2',
            'costo_unitario' => 'decimal:2',
            'costo_total' => 'decimal:2',
            'fecha_vencimiento' => 'date',
        ];
    }

    public function ventaDetalle(): BelongsTo
    {
        return $this->belongsTo(VentaDetalle::class, 'venta_detalle_id');
    }

    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class);
    }

    public function articuloLote(): BelongsTo
    {
        return $this->belongsTo(ArticuloLote::class, 'articulo_lote_id');
    }
}
