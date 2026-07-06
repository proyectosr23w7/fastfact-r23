<?php

namespace App\Models;

use App\Models\Configuracion\PuntoVenta;
use App\Models\Configuracion\Sucursal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventoSignificativo extends Model
{
    protected $table = 'eventos_significativos';

    protected $fillable = [
        'sucursal_id',
        'punto_venta_id',
        'ambiente_facturacion',
        'tipo_contingencia',
        'modo_activacion',
        'cufd_evento_id',
        'cufd_recuperacion_id',
        'cafc_id',
        'codigo_evento',
        'descripcion',
        'fecha_inicio',
        'fecha_fin',
        'estado',
        'codigo_recepcion',
        'registrado_siat_at',
        'datos_respuesta_siat',
        'observacion_interna',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'datetime',
            'fecha_fin' => 'datetime',
            'registrado_siat_at' => 'datetime',
            'datos_respuesta_siat' => 'array',
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

    public function cufdEvento(): BelongsTo
    {
        return $this->belongsTo(Cufd::class, 'cufd_evento_id');
    }

    public function cufdRecuperacion(): BelongsTo
    {
        return $this->belongsTo(Cufd::class, 'cufd_recuperacion_id');
    }

    public function cafc(): BelongsTo
    {
        return $this->belongsTo(Cafc::class, 'cafc_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function facturas(): HasMany
    {
        return $this->hasMany(Factura::class);
    }

    public function reportes(): HasMany
    {
        return $this->hasMany(EventoSignificativoReporte::class);
    }

    public function paquetes(): HasMany
    {
        return $this->hasMany(EventoSignificativoPaquete::class);
    }
}
