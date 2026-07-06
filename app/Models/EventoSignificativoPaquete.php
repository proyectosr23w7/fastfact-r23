<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventoSignificativoPaquete extends Model
{
    protected $table = 'evento_significativo_paquetes';

    protected $fillable = [
        'evento_significativo_id',
        'cufd_envio_id',
        'numero_paquete',
        'cantidad_facturas',
        'codigo_recepcion',
        'codigo_estado',
        'descripcion_estado',
        'estado',
        'hash_archivo',
        'nombre_archivo',
        'fecha_envio',
        'fecha_validacion',
        'datos_respuesta_recepcion',
        'datos_respuesta_validacion',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha_envio' => 'datetime',
            'fecha_validacion' => 'datetime',
            'datos_respuesta_recepcion' => 'array',
            'datos_respuesta_validacion' => 'array',
        ];
    }

    public function eventoSignificativo(): BelongsTo
    {
        return $this->belongsTo(EventoSignificativo::class);
    }

    public function cufdEnvio(): BelongsTo
    {
        return $this->belongsTo(Cufd::class, 'cufd_envio_id');
    }

    public function facturas(): HasMany
    {
        return $this->hasMany(Factura::class, 'evento_significativo_paquete_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
