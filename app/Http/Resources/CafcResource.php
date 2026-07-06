<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class CafcResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $resumen = $this->buildResumen();

        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'pin' => $this->pin,
            'descripcion' => $this->descripcion,
            'sucursal_id' => $this->sucursal_id,
            'punto_venta_id' => $this->punto_venta_id,
            'ambiente_facturacion' => $this->ambiente_facturacion,
            'fecha_inicio_vigencia' => optional($this->fecha_inicio_vigencia)?->format('Y-m-d H:i:s'),
            'fecha_fin_vigencia' => optional($this->fecha_fin_vigencia)?->format('Y-m-d H:i:s'),
            'numero_inicial' => $this->numero_inicial,
            'numero_final' => $this->numero_final,
            'estado' => (bool) $this->estado,
            'observacion' => $this->observacion,
            'resumen' => $resumen,
            'sucursal' => $this->whenLoaded('sucursal', fn () => [
                'id' => $this->sucursal?->id,
                'codigo' => $this->sucursal?->codigo,
                'nombre' => $this->sucursal?->nombre,
            ]),
            'punto_venta' => $this->whenLoaded('puntoVenta', fn () => [
                'id' => $this->puntoVenta?->id,
                'codigo' => $this->puntoVenta?->codigo,
                'nombre' => $this->puntoVenta?->nombre,
            ]),
            'usuario' => $this->whenLoaded('user', fn () => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'email' => $this->user?->email,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function buildResumen(): array
    {
        $ahora = now(config('app.timezone'));
        $inicio = $this->fecha_inicio_vigencia ? Carbon::parse($this->fecha_inicio_vigencia, config('app.timezone'))->timezone(config('app.timezone')) : null;
        $fin = $this->fecha_fin_vigencia ? Carbon::parse($this->fecha_fin_vigencia, config('app.timezone'))->timezone(config('app.timezone')) : null;
        $rangoTotal = null;
        $restantes = null;
        $siguienteNumero = null;
        $ultimoNumero = $this->ultimo_numero_factura !== null ? (int) $this->ultimo_numero_factura : null;
        $utilizados = (int) ($this->total_facturas_count ?? 0);

        if ($this->numero_inicial !== null && $this->numero_final !== null && $this->numero_final >= $this->numero_inicial) {
            $rangoTotal = ($this->numero_final - $this->numero_inicial) + 1;
            $restantes = max($rangoTotal - $utilizados, 0);
            $siguienteNumero = $ultimoNumero !== null
                ? max($ultimoNumero + 1, (int) $this->numero_inicial)
                : (int) $this->numero_inicial;
        }

        $estadoOperativo = 'activo';
        $estadoLabel = 'Activo';

        if (! $this->estado) {
            $estadoOperativo = 'inactivo';
            $estadoLabel = 'Inactivo';
        } elseif ($inicio && $inicio->gt($ahora)) {
            $estadoOperativo = 'programado';
            $estadoLabel = 'Programado';
        } elseif ($fin && $fin->lt($ahora)) {
            $estadoOperativo = 'vencido';
            $estadoLabel = 'Vencido';
        } elseif ($restantes !== null && $restantes <= 0) {
            $estadoOperativo = 'agotado';
            $estadoLabel = 'Agotado';
        } elseif ($fin && $ahora->diffInDays($fin, false) <= 3) {
            $estadoOperativo = 'por_vencer';
            $estadoLabel = 'Por vencer';
        } elseif ($restantes !== null && $restantes <= 10) {
            $estadoOperativo = 'por_agotarse';
            $estadoLabel = 'Por agotarse';
        }

        return [
            'estado_operativo' => $estadoOperativo,
            'estado_label' => $estadoLabel,
            'vigente_hoy' => $this->estado
                && (! $inicio || $inicio->lte($ahora))
                && (! $fin || $fin->gte($ahora)),
            'dias_para_vencer' => $fin ? $ahora->diffInDays($fin, false) : null,
            'rango_total' => $rangoTotal,
            'facturas_utilizadas' => $utilizados,
            'facturas_sincronizadas' => (int) ($this->facturas_sincronizadas_count ?? 0),
            'facturas_pendientes' => (int) ($this->facturas_pendientes_count ?? 0),
            'ultimo_numero_factura' => $ultimoNumero,
            'siguiente_numero_sugerido' => $siguienteNumero,
            'disponibles_restantes' => $restantes,
        ];
    }
}
