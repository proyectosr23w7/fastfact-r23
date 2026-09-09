<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Articulo extends Model
{
    protected $table = 'articulos';

    protected $fillable = [
        'codigo_generico',
        'codigo_barras',
        'nombre',
        'descripcion',
        'tags',
        'alias',
        'atributos',
        'categoria_id',
        'marca_id',
        'unidad_medida_id',
        'codigo_actividad_economica',
        'codigo_producto_sin',
        'codigo_unidad_medida_siat',
        'stock_minimo',
        'stock_actual',
        'costo',
        'precio_base',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'stock_minimo' => 'decimal:2',
            'stock_actual' => 'decimal:2',
            'costo' => 'decimal:2',
            'precio_base' => 'decimal:2',
            'estado' => 'boolean',
            'tags' => 'array',
            'alias' => 'array',
            'atributos' => 'array',
        ];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class);
    }

    public function unidadMedida(): BelongsTo
    {
        return $this->belongsTo(UnidadMedida::class);
    }

    public function precios(): HasMany
    {
        return $this->hasMany(ArticuloPrecio::class);
    }

    public function facturaDetalles(): HasMany
    {
        return $this->hasMany(FacturaDetalle::class);
    }

}
