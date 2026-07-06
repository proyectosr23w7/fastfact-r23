<?php

namespace App\Models;

use App\Enums\FacturaEstadoEnum;
use App\Models\Configuracion\PuntoVenta;
use App\Models\Configuracion\Sucursal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Factura extends Model
{
    protected $table = 'facturas';

    protected $fillable = [
        'origen',
        'referencia_externa',
        'venta_id',
        'cliente_id',
        'sucursal_id',
        'punto_venta_id',
        'user_id',
        'cuis_id',
        'cufd_id',
        'evento_significativo_id',
        'evento_significativo_paquete_id',
        'cafc_id',
        'numero_factura',
        'cuf',
        'codigo_recepcion',
        'codigo_metodo_pago',
        'numero_tarjeta',
        'monto_gift_card',
        'descuento_global',
        'codigo_documento_identidad',
        'tipo_facturacion',
        'ambiente_facturacion',
        'codigo_emision',
        'xml_generado_path',
        'xml_firmado_path',
        'pdf_path',
        'hash_xml',
        'xml_fiscal',
        'fecha_emision',
        'monto_total',
        'monto_sujeto_iva',
        'codigo_estado',
        'descripcion_estado',
        'estado_factura',
        'estado_sincronizacion',
        'codigo_excepcion',
        'observacion',
        'datos_respuesta_siat',
        'anulacion_revertida_at',
        'anulacion_revertida_user_id',
        'anulacion_reversion_codigo_respuesta',
        'anulacion_reversion_descripcion',
        'anulacion_reversion_respuesta_siat',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'fecha_emision' => 'datetime',
            'monto_total' => 'decimal:2',
            'monto_sujeto_iva' => 'decimal:2',
            'monto_gift_card' => 'decimal:2',
            'descuento_global' => 'decimal:2',
            'tipo_facturacion' => 'integer',
            'codigo_emision' => 'integer',
            'datos_respuesta_siat' => 'array',
            'anulacion_revertida_at' => 'datetime',
            'anulacion_reversion_respuesta_siat' => 'array',
            'metadata' => 'array',
            'estado_factura' => FacturaEstadoEnum::class,
        ];
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(VentaCabecera::class, 'venta_id');
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

    public function cuis(): BelongsTo
    {
        return $this->belongsTo(Cuis::class);
    }

    public function cufd(): BelongsTo
    {
        return $this->belongsTo(Cufd::class);
    }

    public function eventoSignificativo(): BelongsTo
    {
        return $this->belongsTo(EventoSignificativo::class);
    }

    public function eventoSignificativoPaquete(): BelongsTo
    {
        return $this->belongsTo(EventoSignificativoPaquete::class, 'evento_significativo_paquete_id');
    }

    public function cafc(): BelongsTo
    {
        return $this->belongsTo(Cafc::class, 'cafc_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(FacturaDetalle::class);
    }

    public function anulaciones(): HasMany
    {
        return $this->hasMany(FacturaAnulacion::class);
    }
}
