<?php

namespace App\Services\Facturacion;

use App\Enums\SiatCatalogoEnum;
use App\Enums\SiatSincronizacionEstadoEnum;
use App\Models\Configuracion\PuntoVenta;
use App\Models\Configuracion\Sucursal;
use App\Models\EventoSignificativo;
use App\Models\SinActividad;
use App\Models\SinDocumentoIdentidad;
use App\Models\SinLeyenda;
use App\Models\SinMetodoPago;
use App\Models\SinMoneda;
use App\Models\SinMotivoAnulacion;
use App\Models\SinProductoServicio;
use App\Models\SinUnidadMedida;
use App\Models\User;
use App\Repositories\Facturacion\SiatSyncRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SiatSyncService
{
    public function __construct(
        private readonly SiatSyncRepository $repository,
        private readonly SiatClientService $client,
    ) {
    }

    public function listar(?User $user = null): Collection
    {
        $context = $this->userContext($user);

        return $this->repository->allForIndex(
            $context['codigo_sucursal'],
            $context['codigo_punto_venta'],
        );
    }

    public function sincronizarCatalogos(array $data, User $user): array
    {
        $this->autorizarContexto($user, (int) $data['sucursal_id'], (int) $data['punto_venta_id']);

        $sucursal = Sucursal::query()->findOrFail($data['sucursal_id']);
        $puntoVenta = PuntoVenta::query()->findOrFail($data['punto_venta_id']);

        $context = [
            'sucursal_id' => $sucursal->id,
            'punto_venta_id' => $puntoVenta->id,
            'codigo_sucursal' => (int) $sucursal->codigo,
            'codigo_punto_venta' => (int) $puntoVenta->codigo,
        ];

        $response = $this->client->sincronizarCatalogos($context);
        $estado = ($response['success'] ?? false) ? SiatSincronizacionEstadoEnum::EXITOSA->value : SiatSincronizacionEstadoEnum::OBSERVADA->value;

        $detalle = collect($response['detalle'] ?? []);

        $logs = Schema::hasTable('siat_sincronizaciones')
            ? collect(SiatCatalogoEnum::cases())->map(function (SiatCatalogoEnum $catalogo) use ($context, $detalle, $estado, $response, $user) {
                $catalogDetail = (array) ($detalle->get($catalogo->value) ?? []);
                $catalogState = ($catalogDetail['success'] ?? false)
                    ? SiatSincronizacionEstadoEnum::EXITOSA->value
                    : $estado;

                return $this->repository->create([
                    'codigo_sucursal' => $context['codigo_sucursal'],
                    'codigo_punto_venta' => $context['codigo_punto_venta'],
                    'tipo_catalogo' => $catalogo->value,
                    'fecha_sincronizacion' => now(),
                    'estado' => $catalogState,
                    'observacion' => $catalogDetail['message'] ?? $response['message'] ?? 'Sin respuesta disponible.',
                    'user_id' => $user->id,
                ]);
            })
            : new SupportCollection();

        $catalogos = $response['catalogos'] ?? [];

        if (Schema::hasTable('sin_actividades')) {
            $this->repository->syncCatalog(SinActividad::class, $catalogos['actividades'] ?? [], ['codigo_clasificador'], ['descripcion', 'estado', 'updated_at']);
        }

        if (Schema::hasTable('sin_productos_servicios')) {
            $this->repository->syncCatalog(SinProductoServicio::class, $catalogos['productos_servicios'] ?? [], ['codigo_producto'], ['codigo_actividad', 'descripcion', 'estado', 'updated_at']);
        }

        if (Schema::hasTable('sin_motivos_anulacion')) {
            $this->repository->syncCatalog(SinMotivoAnulacion::class, $catalogos['motivos_anulacion'] ?? [], ['codigo_clasificador'], ['descripcion', 'estado', 'updated_at']);
        }

        if (Schema::hasTable('sin_leyendas')) {
            // Las leyendas no traen un identificador estable confiable para upsert local,
            // por eso se reemplaza el catalogo completo en cada sincronizacion.
            $this->repository->replaceCatalog(SinLeyenda::class, $catalogos['leyendas'] ?? []);
        }

        if (Schema::hasTable('sin_documentos_identidad')) {
            $this->repository->syncCatalog(SinDocumentoIdentidad::class, $catalogos['documentos_identidad'] ?? [], ['codigo_clasificador'], ['descripcion', 'estado', 'updated_at']);
        }

        if (Schema::hasTable('sin_unidades_medida')) {
            $this->repository->syncCatalog(SinUnidadMedida::class, $catalogos['unidades_medida'] ?? [], ['codigo_clasificador'], ['descripcion', 'estado', 'updated_at']);
        }

        if (Schema::hasTable('sin_monedas')) {
            $this->repository->syncCatalog(SinMoneda::class, $catalogos['monedas'] ?? [], ['codigo_clasificador'], ['descripcion', 'estado', 'updated_at']);
        }

        if (Schema::hasTable('sin_metodos_pago')) {
            $this->repository->syncCatalog(SinMetodoPago::class, $catalogos['metodos_pago'] ?? [], ['codigo_clasificador'], ['descripcion', 'estado', 'updated_at']);
        }

        return [
            'logs' => $logs,
            'response' => $response,
        ];
    }

    public function meta(?User $user = null): array
    {
        $hasSinMetodoPago = Schema::hasTable('sin_metodos_pago');
        $hasSinUnidadMedida = Schema::hasTable('sin_unidades_medida');
        $hasUnidadOperativa = $hasSinUnidadMedida && Schema::hasColumn('sin_unidades_medida', 'habilitado_uso');
        $context = $this->userContext($user);
        $sucursales = Sucursal::query()->where('estado', true)->orderBy('codigo');
        $puntosVenta = PuntoVenta::query()->where('estado', true)->orderBy('sucursal_id')->orderBy('codigo');

        return [
            'catalogos' => array_map(
                fn (SiatCatalogoEnum $catalogo) => ['label' => str_replace('_', ' ', ucfirst($catalogo->value)), 'value' => $catalogo->value],
                SiatCatalogoEnum::cases(),
            ),
            'catalogos_totales' => count(SiatCatalogoEnum::cases()),
            'sucursales' => $sucursales
                ->get(['id', 'codigo', 'nombre'])
                ->map(fn (Sucursal $sucursal) => [
                    'id' => $sucursal->id,
                    'codigo' => $sucursal->codigo,
                    'nombre' => $sucursal->nombre,
                ])
                ->values(),
            'puntos_venta' => $puntosVenta
                ->get(['id', 'sucursal_id', 'codigo', 'nombre'])
                ->map(fn (PuntoVenta $puntoVenta) => [
                    'id' => $puntoVenta->id,
                    'sucursal_id' => $puntoVenta->sucursal_id,
                    'codigo' => $puntoVenta->codigo,
                    'nombre' => $puntoVenta->nombre,
                ])
                ->values(),
            'metodos_pago_activos' => $hasSinMetodoPago
                ? SinMetodoPago::query()
                    ->where('estado', true)
                    ->where('habilitado_venta', true)
                    ->orderByDesc('es_predeterminado')
                    ->orderBy('orden_operativo')
                    ->orderBy('descripcion')
                    ->get(['id', 'codigo_clasificador', 'descripcion', 'habilitado_venta', 'es_predeterminado', 'orden_operativo'])
                    ->values()
                : collect(),
            'unidades_medida_activas' => $hasSinUnidadMedida
                ? SinUnidadMedida::query()
                    ->where('estado', true)
                    ->when($hasUnidadOperativa, fn ($query) => $query->where('habilitado_uso', true))
                    ->orderBy('descripcion')
                    ->get(['id', 'codigo_clasificador', 'descripcion'])
                    ->values()
                : collect(),
            'metodos_pago' => $hasSinMetodoPago
                ? SinMetodoPago::query()
                    ->orderByDesc('habilitado_venta')
                    ->orderByDesc('es_predeterminado')
                    ->orderBy('orden_operativo')
                    ->orderBy('descripcion')
                    ->get(['id', 'codigo_clasificador', 'descripcion', 'estado', 'habilitado_venta', 'es_predeterminado', 'orden_operativo'])
                    ->values()
                : collect(),
            'unidades_medida' => $hasSinUnidadMedida
                ? SinUnidadMedida::query()
                    ->orderBy('descripcion')
                    ->get(array_values(array_filter([
                        'id',
                        'codigo_clasificador',
                        'descripcion',
                        'estado',
                        $hasUnidadOperativa ? 'habilitado_uso' : null,
                    ])))
                    ->map(function (SinUnidadMedida $unidad) use ($hasUnidadOperativa) {
                        if (! $hasUnidadOperativa) {
                            $unidad->setAttribute('habilitado_uso', (bool) $unidad->estado);
                        }

                        return $unidad;
                    })
                    ->values()
                : collect(),
            'siat' => $this->client->profile(),
            'contingencias_activas' => $this->contingenciasActivas($context),
            'puede_gestionar_catalogos' => (bool) ($user?->hasPermission('facturacion.catalogos.manage') ?? false),
            'puede_sincronizar' => (bool) ($user?->hasPermission('facturacion.siat.sync') ?? false),
        ];
    }

    public function autorizarGestionCatalogos(User $user): void
    {
        abort_unless(
            $user->hasPermission('facturacion.catalogos.manage'),
            403,
            'Solo un usuario administrador puede cambiar la disponibilidad operativa de catalogos SIAT.',
        );
    }

    public function cambiarEstadoMetodoPago(SinMetodoPago $metodoPago, bool $estado): SinMetodoPago
    {
        if ($estado && ! $metodoPago->estado) {
            abort(422, 'El metodo de pago esta inactivo en el catalogo SIAT y no puede mostrarse en ventas.');
        }

        DB::transaction(function () use ($metodoPago, $estado) {
            $locked = SinMetodoPago::query()->lockForUpdate()->findOrFail($metodoPago->id);
            $locked->update([
                'habilitado_venta' => $estado,
                'es_predeterminado' => $estado ? $locked->es_predeterminado : false,
            ]);
            $this->ensureDefaultMetodoPago();
        });

        return $metodoPago->refresh();
    }

    public function actualizarMetodoPagoOperativo(SinMetodoPago $metodoPago, array $data): SinMetodoPago
    {
        if ((bool) ($data['es_predeterminado'] ?? false) && ! $metodoPago->estado) {
            abort(422, 'El metodo de pago esta inactivo en el catalogo SIAT y no puede ser predeterminado.');
        }

        DB::transaction(function () use ($metodoPago, $data) {
            $locked = SinMetodoPago::query()->lockForUpdate()->findOrFail($metodoPago->id);

            if (array_key_exists('es_predeterminado', $data) && (bool) $data['es_predeterminado']) {
                SinMetodoPago::query()->where('id', '<>', $locked->id)->update(['es_predeterminado' => false]);
                $data['habilitado_venta'] = true;
            }

            if (array_key_exists('habilitado_venta', $data) && ! (bool) $data['habilitado_venta'] && $locked->es_predeterminado) {
                $data['es_predeterminado'] = false;
            }

            $locked->update($data);
            $this->ensureDefaultMetodoPago();
        });

        return $metodoPago->refresh();
    }

    public function cambiarEstadoUnidadMedida(SinUnidadMedida $unidadMedida, bool $estado): SinUnidadMedida
    {
        if ($estado && ! $unidadMedida->estado) {
            abort(422, 'La unidad de medida esta inactiva en el catalogo SIAT y no puede habilitarse para uso local.');
        }

        $unidadMedida->update(['habilitado_uso' => $estado]);

        return $unidadMedida->refresh();
    }

    private function ensureDefaultMetodoPago(): void
    {
        $hasDefault = SinMetodoPago::query()
            ->where('estado', true)
            ->where('habilitado_venta', true)
            ->where('es_predeterminado', true)
            ->exists();

        if ($hasDefault) {
            return;
        }

        $fallback = SinMetodoPago::query()
            ->where('estado', true)
            ->where('habilitado_venta', true)
            ->orderBy('orden_operativo')
            ->orderBy('descripcion')
            ->first();

        if ($fallback) {
            $fallback->update(['es_predeterminado' => true]);
        }
    }

    private function autorizarContexto(User $user, int $sucursalId, int $puntoVentaId): void
    {
        abort_unless(
            $user->hasPermission('facturacion.siat.sync'),
            403,
            'No tienes permiso para sincronizar catalogos SIAT.',
        );
    }

    private function userContext(?User $user): array
    {
        return [
            'codigo_sucursal' => null,
            'codigo_punto_venta' => null,
        ];
    }

    private function contingenciasActivas(array $context): SupportCollection
    {
        if (! Schema::hasTable('eventos_significativos')) {
            return collect();
        }

        return EventoSignificativo::query()
            ->with(['sucursal:id,codigo,nombre', 'puntoVenta:id,sucursal_id,codigo,nombre'])
            ->where('estado', 'activo_local')
            ->latest('fecha_inicio')
            ->get()
            ->map(fn (EventoSignificativo $evento) => [
                'id' => $evento->id,
                'sucursal_id' => $evento->sucursal_id,
                'punto_venta_id' => $evento->punto_venta_id,
                'descripcion' => $evento->descripcion,
                'tipo_contingencia' => $evento->tipo_contingencia,
                'fecha_inicio' => optional($evento->fecha_inicio)?->toIso8601String(),
                'sucursal' => $evento->sucursal?->nombre,
                'punto_venta' => $evento->puntoVenta?->nombre,
            ])
            ->values();
    }
}
