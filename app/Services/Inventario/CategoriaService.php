<?php

namespace App\Services\Inventario;

use App\Models\Categoria;
use App\Repositories\Inventario\CategoriaRepository;
use Illuminate\Database\Eloquent\Collection;

class CategoriaService
{
    public function __construct(
        private readonly CategoriaRepository $repository,
    ) {
    }

    public function listar(): Collection
    {
        return $this->repository->allForIndex();
    }

    public function crear(array $data): Categoria
    {
        $data['estado'] = $data['estado'] ?? true;

        return $this->repository->create($data);
    }

    public function actualizar(Categoria $categoria, array $data): Categoria
    {
        return $this->repository->update($categoria, $data);
    }

    public function cambiarEstado(Categoria $categoria, bool $estado): Categoria
    {
        return $this->repository->update($categoria, ['estado' => $estado]);
    }

    public function eliminar(Categoria $categoria): void
    {
        $this->repository->delete($categoria);
    }
}
