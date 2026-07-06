<?php

namespace App\Models;

use App\Models\Configuracion\PuntoVenta;
use App\Models\Configuracion\Sucursal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cuis extends Model
{
    protected $table = 'cuis';

    protected $fillable = [
        'codigo',
        'sucursal_id',
        'punto_venta_id',
        'ambiente_facturacion',
        'fecha_vigencia',
        'estado',
        'codigo_respuesta',
        'descripcion_respuesta',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha_vigencia' => 'datetime',
            'estado' => 'boolean',
        ];
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function puntoVenta(): BelongsTo
    {
        return $this->belongsTo(PuntoVenta::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function facturas(): HasMany
    {
        return $this->hasMany(Factura::class);
    }
}
