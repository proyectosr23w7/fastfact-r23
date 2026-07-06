<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacturaAnulacion extends Model
{
    protected $table = 'factura_anulaciones';

    protected $fillable = [
        'factura_id',
        'codigo_motivo_anulacion',
        'descripcion_motivo',
        'fecha_anulacion',
        'codigo_respuesta',
        'descripcion_respuesta',
        'datos_respuesta_siat',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha_anulacion' => 'datetime',
            'datos_respuesta_siat' => 'array',
        ];
    }

    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
