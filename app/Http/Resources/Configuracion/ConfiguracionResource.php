<?php

namespace App\Http\Resources\Configuracion;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConfiguracionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'facturacion_habilitada' => (bool) $this->facturacion_habilitada,
            'tipo_facturacion' => (int) $this->tipo_facturacion,
            'ambiente_facturacion' => $this->ambiente_facturacion,
            'token_siat_configurado' => filled($this->token_siat),
            'codigo_sistema' => $this->codigo_sistema,
            'token_siat_piloto_configurado' => filled($this->token_siat_piloto),
            'token_siat_piloto_vigencia' => optional($this->token_siat_piloto_vigencia)?->format('Y-m-d\TH:i'),
            'token_siat_produccion_configurado' => filled($this->token_siat_produccion),
            'token_siat_produccion_vigencia' => optional($this->token_siat_produccion_vigencia)?->format('Y-m-d\TH:i'),
            'firma_digital_nombre' => $this->firma_digital_nombre,
            'firma_digital_configurada' => $this->firmaDigitalConfigurada(),
            'metodo_salida' => $this->metodo_salida,
            'metodo_costos' => $this->metodo_costos,
            'multiples_precios' => (bool) $this->multiples_precios,
            'precios_por_cantidad' => (bool) $this->precios_por_cantidad,
            'productos_categorias_habilitadas' => (bool) ($this->productos_categorias_habilitadas ?? true),
            'productos_marcas_habilitadas' => (bool) ($this->productos_marcas_habilitadas ?? false),
            'productos_busqueda_avanzada_habilitada' => (bool) ($this->productos_busqueda_avanzada_habilitada ?? false),
            'productos_codigo_barras_habilitado' => (bool) ($this->productos_codigo_barras_habilitado ?? true),
            'pagos_credito_habilitados' => (bool) $this->pagos_credito_habilitados,
            'confirmacion_rapida_ventas' => (bool) $this->confirmacion_rapida_ventas,
            'facturacion_obligatoria_ventas' => (bool) $this->facturacion_obligatoria_ventas,
            'estado' => (bool) $this->estado,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
