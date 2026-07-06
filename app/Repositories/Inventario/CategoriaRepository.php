<?php

namespace App\Repositories\Inventario;

use App\Models\Categoria;
use Illuminate\Database\Eloquent\Collection;

class CategoriaRepository
{
    public function allForIndex(): Collection
    {
        return Categoria::query()
            ->withCount('articulos')
            ->orderBy('nombre')
            ->get();
    }

    public function activeOptions(): Collection
    {
        return Categoria::query()
            ->where('estado', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);
    }

    public function create(array $data): Categoria
    {
        return Categoria::query()->create($data);
    }

    public function update(Categoria $categoria, array $data): Categoria
    {
        $categoria->update($data);

        return $categoria->refresh();
    }

    public function delete(Categoria $categoria): void
    {
        $categoria->delete();
    }
}
