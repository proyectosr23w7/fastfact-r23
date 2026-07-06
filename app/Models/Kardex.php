<?php

namespace App\Models;

use App\Models\Configuracion\Sucursal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Kardex extends Model
{
    protected $table = 'kardex';

    protected $fillable = [
        'articulo_id',
        'sucursal_id',
        'tipo_movimiento',
        'referencia_tipo',
        'referencia_id',
        'fecha',
        'entrada',
        'salida',
        'saldo',
        'costo_unitario',
        'costo_total',
        'stock_anterior',
        'stock_nuevo',
        'observacion',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'datetime',
            'entrada' => 'decimal:2',
            'salida' => 'decimal:2',
            'saldo' => 'decimal:2',
            'costo_unitario' => 'decimal:2',
            'costo_total' => 'decimal:2',
            'stock_anterior' => 'decimal:2',
            'stock_nuevo' => 'decimal:2',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
