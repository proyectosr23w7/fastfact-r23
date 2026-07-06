<?php

namespace App\Models;

use App\Models\Configuracion\PuntoVenta;
use App\Models\Configuracion\Sucursal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventoSignificativoReporte extends Model
{
    protected $table = 'evento_significativo_reportes';

    protected $fillable = [
        'evento_significativo_id',
        'sucursal_id',
        'punto_venta_id',
        'ambiente_facturacion',
        'tipo_falla',
        'descripcion',
        'user_id',
    ];

    public function eventoSignificativo(): BelongsTo
    {
        return $this->belongsTo(EventoSignificativo::class);
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
}
