<?php

namespace App\Models;

use App\Models\Configuracion\PuntoVenta;
use App\Models\Configuracion\Sucursal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cafc extends Model
{
    protected $table = 'cafc';

    protected $fillable = [
        'codigo',
        'pin',
        'descripcion',
        'sucursal_id',
        'punto_venta_id',
        'ambiente_facturacion',
        'fecha_inicio_vigencia',
        'fecha_fin_vigencia',
        'numero_inicial',
        'numero_final',
        'estado',
        'observacion',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio_vigencia' => 'datetime',
            'fecha_fin_vigencia' => 'datetime',
            'numero_inicial' => 'integer',
            'numero_final' => 'integer',
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

    public function eventosSignificativos(): HasMany
    {
        return $this->hasMany(EventoSignificativo::class);
    }

    public function facturas(): HasMany
    {
        return $this->hasMany(Factura::class);
    }
}
