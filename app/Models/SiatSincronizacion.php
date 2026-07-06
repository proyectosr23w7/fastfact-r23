<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiatSincronizacion extends Model
{
    protected $table = 'siat_sincronizaciones';

    protected $fillable = [
        'codigo_sucursal',
        'codigo_punto_venta',
        'tipo_catalogo',
        'fecha_sincronizacion',
        'estado',
        'observacion',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha_sincronizacion' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
