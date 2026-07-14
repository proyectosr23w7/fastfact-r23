<?php

namespace App\Repositories\Inventario;

use App\Models\Articulo;
use Illuminate\Database\Eloquent\Collection;

class ArticuloRepository
{
    public function allForIndex(array $filters = []): Collection
    {
        return Articulo::query()
            ->with([
                'categoria:id,nombre',
                'marca:id,nombre',
                'unidadMedida:id,nombre,abreviatura',
                'precios:id,articulo_id,cantidad_minima,precio,tipo_precio,estado',
            ])
            ->when(
                filled($filters['search'] ?? null),
                function ($query) use ($filters): void {
                    $search = trim((string) $filters['search']);

                    $query->where(function ($subQuery) use ($search): void {
                        $subQuery
                            ->where('codigo_generico', 'like', "%{$search}%")
                            ->orWhere('codigo_barras', 'like', "%{$search}%")
                            ->orWhere('nombre', 'like', "%{$search}%")
                            ->orWhere('descripcion', 'like', "%{$search}%")
                            ->orWhere('tags', 'like', "%{$search}%")
                            ->orWhere('alias', 'like', "%{$search}%")
                            ->orWhere('atributos', 'like', "%{$search}%")
                            ->orWhereHas('categoria', fn ($categoria) => $categoria->where('nombre', 'like', "%{$search}%"))
                            ->orWhereHas('marca', fn ($marca) => $marca->where('nombre', 'like', "%{$search}%"));
                    })
                        ->orderByRaw(
                            "CASE
                                WHEN codigo_generico = ? OR codigo_barras = ? THEN 1
                                WHEN LOWER(nombre) = LOWER(?) THEN 2
                                WHEN `alias` LIKE ? OR tags LIKE ? OR atributos LIKE ? THEN 3
                                WHEN EXISTS (
                                    SELECT 1 FROM categorias
                                    WHERE categorias.id = articulos.categoria_id
                                    AND categorias.nombre LIKE ?
                                )
                                OR EXISTS (
                                    SELECT 1 FROM marcas
                                    WHERE marcas.id = articulos.marca_id
                                    AND marcas.nombre LIKE ?
                                ) THEN 4
                                WHEN descripcion LIKE ? THEN 5
                                ELSE 6
                            END",
                            [$search, $search, $search, "%{$search}%", "%{$search}%", "%{$search}%", "%{$search}%", "%{$search}%", "%{$search}%"],
                        )
                        ->orderBy('nombre');
                },
            )
            ->when(
                filled($filters['categoria_id'] ?? null),
                fn ($query) => $query->where('categoria_id', $filters['categoria_id']),
            )
            ->when(
                filled($filters['marca_id'] ?? null),
                fn ($query) => $query->where('marca_id', $filters['marca_id']),
            )
            ->when(
                ($filters['stock'] ?? null) === 'disponible',
                fn ($query) => $query->where('stock_actual', '>', 0),
            )
            ->when(
                ($filters['stock'] ?? null) === 'bajo',
                fn ($query) => $query
                    ->where('stock_actual', '>', 0)
                    ->whereColumn('stock_actual', '<=', 'stock_minimo'),
            )
            ->when(
                ($filters['stock'] ?? null) === 'sin_existencias',
                fn ($query) => $query->where('stock_actual', '<=', 0),
            )
            ->orderBy('nombre')
            ->get();
    }

    public function inventorySummary(): array
    {
        $summary = Articulo::query()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN stock_actual > 0 AND stock_actual <= stock_minimo THEN 1 ELSE 0 END) as stock_bajo')
            ->selectRaw('SUM(CASE WHEN stock_actual <= 0 THEN 1 ELSE 0 END) as sin_existencias')
            ->selectRaw('SUM(stock_actual * costo) as valor_inventario')
            ->first();

        return [
            'total' => (int) ($summary?->total ?? 0),
            'stock_bajo' => (int) ($summary?->stock_bajo ?? 0),
            'sin_existencias' => (int) ($summary?->sin_existencias ?? 0),
            'valor_inventario' => (float) ($summary?->valor_inventario ?? 0),
        ];
    }

    public function create(array $data): Articulo
    {
        return Articulo::query()->create($data);
    }

    public function update(Articulo $articulo, array $data): Articulo
    {
        $articulo->update($data);

        return $this->refresh($articulo);
    }

    public function delete(Articulo $articulo): void
    {
        $articulo->delete();
    }

    public function refresh(Articulo $articulo): Articulo
    {
        return $articulo->refresh()->load([
            'categoria:id,nombre',
            'marca:id,nombre',
            'unidadMedida:id,nombre,abreviatura',
            'precios:id,articulo_id,cantidad_minima,precio,tipo_precio,estado',
        ]);
    }
}
