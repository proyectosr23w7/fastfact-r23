<?php

namespace App\Services\Inventario;

use App\Models\Articulo;
use App\Models\Categoria;
use App\Models\Configuracion\Configuracion;
use App\Models\SinActividad;
use App\Models\SinProductoServicio;
use App\Models\SinUnidadMedida;
use App\Models\UnidadMedida;
use App\Repositories\Inventario\ArticuloRepository;
use App\Repositories\Inventario\CategoriaRepository;
use App\Repositories\Inventario\MarcaRepository;
use App\Repositories\Inventario\UnidadMedidaRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ArticuloService
{
    public function __construct(
        private readonly ArticuloRepository $repository,
        private readonly CategoriaRepository $categoriaRepository,
        private readonly MarcaRepository $marcaRepository,
        private readonly UnidadMedidaRepository $unidadMedidaRepository,
        private readonly ArticuloPrecioService $articuloPrecioService,
    ) {
    }

    public function listar(array $filters = []): Collection
    {
        return $this->repository->allForIndex($filters);
    }

    public function crear(array $data): Articulo
    {
        return DB::transaction(function () use ($data): Articulo {
            $precios = Arr::pull($data, 'precios', []);
            $configuracion = Configuracion::ensureCurrent();
            $data['estado'] = $data['estado'] ?? true;
            $data = $this->aplicarConfiguracionProducto($data, $configuracion);
            $data['unidad_medida_id'] = $this->resolverUnidadMedidaSiat($data['codigo_unidad_medida_siat'] ?? null);
            $data['stock_actual'] = 0;
            $data['costo'] = 0;
            $data['tags'] = $this->normalizarTerminos($data['tags'] ?? []);
            $data['alias'] = $this->normalizarTerminos($data['alias'] ?? []);
            $data['atributos'] = $this->normalizarAtributos($data['atributos'] ?? []);

            $articulo = $this->repository->create($data);
            $this->articuloPrecioService->syncForArticulo($articulo, $precios);

            return $this->repository->refresh($articulo);
        });
    }

    public function actualizar(Articulo $articulo, array $data): Articulo
    {
        return DB::transaction(function () use ($articulo, $data): Articulo {
            $precios = Arr::pull($data, 'precios', []);
            $configuracion = Configuracion::ensureCurrent();
            unset($data['stock_actual'], $data['costo']);
            $data = $this->aplicarConfiguracionProducto($data, $configuracion);
            $data['unidad_medida_id'] = $this->resolverUnidadMedidaSiat($data['codigo_unidad_medida_siat'] ?? null);
            $data['tags'] = $this->normalizarTerminos($data['tags'] ?? []);
            $data['alias'] = $this->normalizarTerminos($data['alias'] ?? []);
            $data['atributos'] = $this->normalizarAtributos($data['atributos'] ?? []);
            $articulo = $this->repository->update($articulo, $data);
            $this->articuloPrecioService->syncForArticulo($articulo, $precios);

            return $this->repository->refresh($articulo);
        });
    }

    public function cambiarEstado(Articulo $articulo, bool $estado): Articulo
    {
        return $this->repository->update($articulo, ['estado' => $estado]);
    }

    public function eliminar(Articulo $articulo): void
    {
        $this->repository->delete($articulo);
    }

    public function meta(): array
    {
        return [
            'resumen' => $this->repository->inventorySummary(),
            'categorias' => $this->categoriaRepository->activeOptions(),
            'marcas' => $this->marcaRepository->activeOptions(),
            'unidades_medida' => $this->unidadMedidaRepository->activeOptions(),
            'configuracion_precios' => [
                'habilitado' => $this->articuloPrecioService->priceFeaturesEnabled(),
            ],
            'configuracion_productos' => $this->configuracionProductos(),
            'siat_actividades' => SinActividad::query()
                ->where('estado', true)
                ->orderBy('codigo_clasificador')
                ->get(['codigo_clasificador', 'descripcion']),
            'siat_productos_servicios' => SinProductoServicio::query()
                ->where('estado', true)
                ->orderBy('codigo_actividad')
                ->orderBy('codigo_producto')
                ->get(['codigo_actividad', 'codigo_producto', 'descripcion']),
            'siat_unidades_medida' => SinUnidadMedida::query()
                ->where('estado', true)
                ->where('habilitado_uso', true)
                ->orderBy('descripcion')
                ->get(['codigo_clasificador', 'descripcion']),
        ];
    }

    private function configuracionProductos(): array
    {
        $configuracion = Configuracion::ensureCurrent();

        return [
            'categorias_habilitadas' => (bool) ($configuracion->productos_categorias_habilitadas ?? true),
            'marcas_habilitadas' => (bool) ($configuracion->productos_marcas_habilitadas ?? false),
            'busqueda_avanzada_habilitada' => (bool) ($configuracion->productos_busqueda_avanzada_habilitada ?? false),
            'codigo_barras_habilitado' => (bool) ($configuracion->productos_codigo_barras_habilitado ?? true),
        ];
    }

    private function aplicarConfiguracionProducto(array $data, Configuracion $configuracion): array
    {
        if (! (bool) ($configuracion->productos_categorias_habilitadas ?? true)) {
            $data['categoria_id'] = $this->categoriaGeneralId();
        }

        if (! (bool) ($configuracion->productos_marcas_habilitadas ?? false)) {
            $data['marca_id'] = null;
        }

        if (! (bool) ($configuracion->productos_codigo_barras_habilitado ?? true)) {
            $data['codigo_barras'] = null;
        }

        if (! (bool) ($configuracion->productos_busqueda_avanzada_habilitada ?? false)) {
            $data['tags'] = [];
            $data['alias'] = [];
            $data['atributos'] = [];
        }

        return $data;
    }

    private function categoriaGeneralId(): int
    {
        return (int) Categoria::query()->firstOrCreate(
            ['nombre' => 'General'],
            ['descripcion' => 'Categoria general para productos sin clasificacion.', 'estado' => true],
        )->id;
    }

    private function resolverUnidadMedidaSiat(?string $codigoUnidadSiat): int
    {
        $codigo = trim((string) $codigoUnidadSiat);

        abort_if($codigo === '', 422, 'Selecciona una unidad de medida.');

        $unidadSiat = SinUnidadMedida::query()
            ->where('estado', true)
            ->where('habilitado_uso', true)
            ->where('codigo_clasificador', $codigo)
            ->firstOrFail();

        $nombre = trim((string) $unidadSiat->descripcion);
        $abreviaturaBase = preg_replace('/[^A-Z0-9]/', '', strtoupper($nombre)) ?: 'UND';
        $abreviatura = substr($abreviaturaBase, 0, 20);

        $unidad = UnidadMedida::query()
            ->where('nombre', $nombre)
            ->orWhere('abreviatura', $abreviatura)
            ->first();

        if ($unidad) {
            if (! $unidad->estado) {
                $unidad->update(['estado' => true]);
            }

            return (int) $unidad->id;
        }

        $sufijo = 1;
        $abreviaturaUnica = $abreviatura;
        while (UnidadMedida::query()->where('abreviatura', $abreviaturaUnica)->exists()) {
            $sufijo++;
            $abreviaturaUnica = substr($abreviatura, 0, max(1, 20 - strlen((string) $sufijo))).$sufijo;
        }

        return (int) UnidadMedida::query()->create([
            'nombre' => $nombre,
            'abreviatura' => $abreviaturaUnica,
            'estado' => true,
        ])->id;
    }

    private function normalizarTerminos(array $terminos): array
    {
        return collect($terminos)
            ->map(fn ($termino) => trim((string) $termino))
            ->filter()
            ->unique(fn ($termino) => mb_strtolower($termino))
            ->values()
            ->all();
    }

    private function normalizarAtributos(array $atributos): array
    {
        return collect($atributos)
            ->map(fn ($atributo) => [
                'atributo' => trim((string) ($atributo['atributo'] ?? '')),
                'valor' => trim((string) ($atributo['valor'] ?? '')),
            ])
            ->filter(fn ($atributo) => $atributo['atributo'] !== '' && $atributo['valor'] !== '')
            ->unique(fn ($atributo) => mb_strtolower($atributo['atributo'].'|'.$atributo['valor']))
            ->values()
            ->all();
    }
}
