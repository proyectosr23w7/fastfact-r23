<?php

namespace App\Models\Configuracion;

use App\Models\Cuis;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PuntoVenta extends Model
{
    protected $table = 'puntos_venta';

    protected $fillable = [
        'sucursal_id',
        'codigo',
        'nombre',
        'descripcion',
        'tipo_impresion',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'estado' => 'boolean',
        ];
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function cuisVigente(): HasOne
    {
        return $this->hasOne(Cuis::class, 'punto_venta_id')
            ->where('estado', true)
            ->where(function ($query) {
                $query->whereNull('fecha_vigencia')
                    ->orWhere('fecha_vigencia', '>=', now());
            })
            ->latest('fecha_vigencia')
            ->latest('id');
    }

}
