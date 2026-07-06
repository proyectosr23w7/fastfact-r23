<?php

namespace App\Models\Configuracion;

use App\Enums\TipoFacturacionEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Configuracion extends Model
{
    protected $table = 'configuraciones';

    protected $fillable = [
        'facturacion_habilitada',
        'tipo_facturacion',
        'ambiente_facturacion',
        'token_siat',
        'codigo_sistema',
        'token_siat_piloto',
        'token_siat_piloto_vigencia',
        'token_siat_produccion',
        'token_siat_produccion_vigencia',
        'firma_digital_path',
        'firma_digital_nombre',
        'firma_digital_password',
        'metodo_salida',
        'metodo_costos',
        'multiples_precios',
        'precios_por_cantidad',
        'productos_categorias_habilitadas',
        'productos_marcas_habilitadas',
        'productos_busqueda_avanzada_habilitada',
        'productos_codigo_barras_habilitado',
        'pagos_credito_habilitados',
        'confirmacion_rapida_ventas',
        'facturacion_obligatoria_ventas',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'facturacion_habilitada' => 'boolean',
            'tipo_facturacion' => 'integer',
            'token_siat_piloto_vigencia' => 'datetime',
            'token_siat_produccion_vigencia' => 'datetime',
            'firma_digital_password' => 'encrypted',
            'multiples_precios' => 'boolean',
            'precios_por_cantidad' => 'boolean',
            'productos_categorias_habilitadas' => 'boolean',
            'productos_marcas_habilitadas' => 'boolean',
            'productos_busqueda_avanzada_habilitada' => 'boolean',
            'productos_codigo_barras_habilitado' => 'boolean',
            'pagos_credito_habilitados' => 'boolean',
            'confirmacion_rapida_ventas' => 'boolean',
            'facturacion_obligatoria_ventas' => 'boolean',
            'estado' => 'boolean',
        ];
    }

    public function tokenSiatActivo(): ?string
    {
        return match ($this->ambiente_facturacion) {
            'produccion' => $this->token_siat_produccion ?: $this->token_siat,
            default => $this->token_siat_piloto ?: $this->token_siat,
        };
    }

    public function tokenSiatVigenciaActiva()
    {
        return match ($this->ambiente_facturacion) {
            'produccion' => $this->token_siat_produccion_vigencia,
            default => $this->token_siat_piloto_vigencia,
        };
    }

    public function facturacionSiatActiva(): bool
    {
        return $this->facturacion_habilitada
            && (int) $this->tipo_facturacion !== TipoFacturacionEnum::NO_EMITE->value;
    }

    public function ventasFacturacionObligatoria(): bool
    {
        return $this->facturacionSiatActiva() && (bool) $this->facturacion_obligatoria_ventas;
    }

    public function firmaDigitalConfigurada(): bool
    {
        return filled($this->firma_digital_path)
            && Storage::disk('local')->exists((string) $this->firma_digital_path)
            && filled($this->firma_digital_password);
    }

    public function firmaDigitalAbsolutePath(): ?string
    {
        if (! filled($this->firma_digital_path)) {
            return null;
        }

        return Storage::disk('local')->path((string) $this->firma_digital_path);
    }

    public static function current(): ?self
    {
        return static::query()
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();
    }

    public static function ensureCurrent(): self
    {
        return static::current() ?? static::query()->create([
            'facturacion_habilitada' => false,
            'tipo_facturacion' => TipoFacturacionEnum::NO_EMITE->value,
            'ambiente_facturacion' => 'piloto',
            'metodo_salida' => 'peps',
            'metodo_costos' => 'promedio_ponderado',
            'multiples_precios' => false,
            'precios_por_cantidad' => false,
            'productos_categorias_habilitadas' => true,
            'productos_marcas_habilitadas' => false,
            'productos_busqueda_avanzada_habilitada' => false,
            'productos_codigo_barras_habilitado' => true,
            'pagos_credito_habilitados' => false,
            'confirmacion_rapida_ventas' => false,
            'facturacion_obligatoria_ventas' => false,
            'estado' => true,
        ]);
    }
}
