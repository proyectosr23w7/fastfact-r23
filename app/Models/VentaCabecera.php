<?php

namespace App\Models;

use App\Enums\TipoDocumentoVentaEnum;
use App\Enums\VentaEstadoEnum;
use App\Models\Configuracion\PuntoVenta;
use App\Models\Configuracion\Sucursal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class VentaCabecera extends Model
{
    protected $table = 'venta_cabeceras';

    protected $fillable = [
        'numero_venta',
        'cliente_id',
        'sucursal_id',
        'punto_venta_id',
        'user_id',
        'fecha_venta',
        'subtotal',
        'descuento',
        'impuesto',
        'iva',
        'it',
        'total',
        'total_costo',
        'utilidad_bruta',
        'observacion',
        'tipo_documento_venta',
        'codigo_metodo_pago',
        'numero_tarjeta',
        'monto_gift_card',
        'requiere_factura',
        'estado',
        'cuf',
        'cufd',
        'codigo_recepcion',
        'numero_factura',
        'codigo_excepcion',
        'estado_facturacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_venta' => 'date',
            'subtotal' => 'decimal:2',
            'descuento' => 'decimal:2',
            'impuesto' => 'decimal:2',
            'iva' => 'decimal:2',
            'it' => 'decimal:2',
            'total' => 'decimal:2',
            'total_costo' => 'decimal:2',
            'utilidad_bruta' => 'decimal:2',
            'monto_gift_card' => 'decimal:2',
            'requiere_factura' => 'boolean',
            'tipo_documento_venta' => TipoDocumentoVentaEnum::class,
            'estado' => VentaEstadoEnum::class,
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
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

    public function detalle(): HasMany
    {
        return $this->hasMany(VentaDetalle::class, 'venta_id');
    }

    public function factura(): HasOne
    {
        return $this->hasOne(Factura::class, 'venta_id');
    }
}
