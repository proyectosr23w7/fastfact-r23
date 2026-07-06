<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacturaDetalle extends Model
{
    protected $table = 'factura_detalles';

    protected $fillable = [
        'factura_id',
        'articulo_id',
        'actividad_economica',
        'codigo_producto_sin',
        'codigo_producto',
        'descripcion',
        'cantidad',
        'unidad_medida',
        'precio_unitario',
        'monto_descuento',
        'subtotal',
        'numero_serie',
        'numero_imei',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'codigo_producto_sin' => 'integer',
            'cantidad' => 'decimal:5',
            'unidad_medida' => 'integer',
            'precio_unitario' => 'decimal:5',
            'monto_descuento' => 'decimal:5',
            'subtotal' => 'decimal:5',
            'metadata' => 'array',
        ];
    }

    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class);
    }

    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class);
    }
}