<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventoSignificativoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $cafc = $this->relationLoaded('cafc') && $this->cafc
            ? CafcResource::make($this->cafc)->resolve()
            : null;

        return [
            'id' => $this->id,
            'sucursal_id' => $this->sucursal_id,
            'punto_venta_id' => $this->punto_venta_id,
            'ambiente_facturacion' => $this->ambiente_facturacion,
            'tipo_contingencia' => $this->tipo_contingencia,
            'modo_activacion' => $this->modo_activacion,
            'cufd_evento_id' => $this->cufd_evento_id,
            'cufd_recuperacion_id' => $this->cufd_recuperacion_id,
            'cafc_id' => $this->cafc_id,
            'codigo_evento' => $this->codigo_evento,
            'descripcion' => $this->descripcion,
            'fecha_inicio' => optional($this->fecha_inicio)?->format('Y-m-d H:i:s'),
            'fecha_fin' => optional($this->fecha_fin)?->format('Y-m-d H:i:s'),
            'estado' => $this->estado,
            'codigo_recepcion' => $this->codigo_recepcion,
            'registrado_siat_at' => optional($this->registrado_siat_at)?->format('Y-m-d H:i:s'),
            'datos_respuesta_siat' => $this->datos_respuesta_siat,
            'observacion_interna' => $this->observacion_interna,
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
            'cufd_evento' => $this->whenLoaded('cufdEvento', fn () => CufdResource::make($this->cufdEvento)->resolve()),
            'cufd_recuperacion' => $this->whenLoaded('cufdRecuperacion', fn () => CufdResource::make($this->cufdRecuperacion)->resolve()),
            'cafc' => $cafc,
            'alerta_cafc' => $this->buildCafcAlert($cafc),
            'facturas' => $this->whenLoaded('facturas', fn () => $this->facturas->map(fn ($factura) => [
                'id' => $factura->id,
                'cliente_id' => $factura->cliente_id,
                'cafc_id' => $factura->cafc_id,
                'numero_factura' => $factura->numero_factura,
                'cuf' => $factura->cuf,
                'codigo_recepcion' => $factura->codigo_recepcion,
                'codigo_emision' => (int) ($factura->codigo_emision ?? 1),
                'estado_factura' => is_string($factura->estado_factura) ? $factura->estado_factura : $factura->estado_factura?->value,
                'estado_sincronizacion' => $factura->estado_sincronizacion,
                'codigo_estado' => $factura->codigo_estado,
                'descripcion_estado' => $factura->descripcion_estado,
                'monto_total' => (float) ($factura->monto_total ?? 0),
                'fecha_emision' => optional($factura->fecha_emision)?->format('Y-m-d H:i:s'),
                'cliente' => $factura->cliente ? [
                    'id' => $factura->cliente->id,
                    'nombre' => $factura->cliente->nombre,
                    'razon_social' => $factura->cliente->razon_social,
                    'nit_ci' => $factura->cliente->nit_ci,
                ] : null,
                'cafc' => $factura->cafc ? [
                    'id' => $factura->cafc->id,
                    'codigo' => $factura->cafc->codigo,
                    'descripcion' => $factura->cafc->descripcion,
                ] : null,
                'usuario' => $factura->user ? [
                    'id' => $factura->user->id,
                    'name' => $factura->user->name,
                ] : null,
            ])->values()),
            'paquetes' => $this->whenLoaded('paquetes', fn () => $this->paquetes->map(fn ($paquete) => [
                'id' => $paquete->id,
                'numero_paquete' => $paquete->numero_paquete,
                'cantidad_facturas' => $paquete->cantidad_facturas,
                'codigo_recepcion' => $paquete->codigo_recepcion,
                'codigo_estado' => $paquete->codigo_estado,
                'descripcion_estado' => $paquete->descripcion_estado,
                'estado' => $paquete->estado,
                'hash_archivo' => $paquete->hash_archivo,
                'nombre_archivo' => $paquete->nombre_archivo,
                'fecha_envio' => optional($paquete->fecha_envio)?->format('Y-m-d H:i:s'),
                'fecha_validacion' => optional($paquete->fecha_validacion)?->format('Y-m-d H:i:s'),
                'cufd_envio' => $paquete->cufdEnvio ? [
                    'id' => $paquete->cufdEnvio->id,
                    'codigo' => $paquete->cufdEnvio->codigo,
                ] : null,
            ])->values()),
            'reportes' => $this->whenLoaded('reportes', fn () => $this->reportes->map(fn ($reporte) => [
                'id' => $reporte->id,
                'tipo_falla' => $reporte->tipo_falla,
                'descripcion' => $reporte->descripcion,
                'usuario' => $reporte->user ? [
                    'id' => $reporte->user->id,
                    'name' => $reporte->user->name,
                    'email' => $reporte->user->email,
                ] : null,
                'created_at' => optional($reporte->created_at)?->format('Y-m-d H:i:s'),
            ])->values()),
            'usuario' => $this->whenLoaded('user', fn () => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'email' => $this->user?->email,
            ]),
            'resumen' => [
                'total_facturas' => (int) ($this->total_facturas_count ?? 0),
                'facturas_pendientes' => (int) ($this->facturas_pendientes_count ?? 0),
                'facturas_sincronizadas' => (int) ($this->facturas_sincronizadas_count ?? 0),
                'total_paquetes' => (int) ($this->total_paquetes_count ?? 0),
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function buildCafcAlert(?array $cafc): ?array
    {
        if (! $cafc || ! is_array($cafc)) {
            return null;
        }

        $resumen = $cafc['resumen'] ?? null;

        if (! is_array($resumen)) {
            return null;
        }

        $estadoOperativo = (string) ($resumen['estado_operativo'] ?? '');
        $estadoLabel = (string) ($resumen['estado_label'] ?? 'CAFC');
        $restantes = $resumen['disponibles_restantes'] ?? null;
        $diasParaVencer = $resumen['dias_para_vencer'] ?? null;

        return match ($estadoOperativo) {
            'por_agotarse' => [
                'tone' => 'warning',
                'label' => $estadoLabel,
                'message' => 'El CAFC tiene pocos numeros disponibles para contingencia manual.',
                'detail' => $restantes !== null ? sprintf('Restantes: %s', $restantes) : null,
            ],
            'por_vencer' => [
                'tone' => 'warning',
                'label' => $estadoLabel,
                'message' => 'El CAFC asociado esta proximo a vencer.',
                'detail' => $diasParaVencer !== null ? sprintf('Dias restantes: %s', $diasParaVencer) : null,
            ],
            'agotado' => [
                'tone' => 'danger',
                'label' => $estadoLabel,
                'message' => 'El CAFC asociado ya agoto su rango autorizado.',
                'detail' => null,
            ],
            'vencido' => [
                'tone' => 'danger',
                'label' => $estadoLabel,
                'message' => 'El CAFC asociado ya se encuentra vencido.',
                'detail' => null,
            ],
            'inactivo' => [
                'tone' => 'danger',
                'label' => $estadoLabel,
                'message' => 'El CAFC asociado esta inactivo y no deberia usarse para contingencia manual.',
                'detail' => null,
            ],
            'programado' => [
                'tone' => 'warning',
                'label' => $estadoLabel,
                'message' => 'El CAFC asociado aun no entra en vigencia.',
                'detail' => null,
            ],
            default => null,
        };
    }
}
