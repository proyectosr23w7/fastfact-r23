<?php

namespace App\Services\Inventario;

use App\Models\Marca;
use App\Repositories\Inventario\MarcaRepository;
use Illuminate\Database\Eloquent\Collection;

class MarcaService
{
    public function __construct(
        private readonly MarcaRepository $repository,
    ) {
    }

    public function listar(): Collection
    {
        return $this->repository->allForIndex();
    }

    public function crear(array $data): Marca
    {
        $data['estado'] = $data['estado'] ?? true;

        return $this->repository->create($data);
    }

    public function actualizar(Marca $marca, array $data): Marca
    {
        return $this->repository->update($marca, $data);
    }

    public function cambiarEstado(Marca $marca, bool $estado): Marca
    {
        return $this->repository->update($marca, ['estado' => $estado]);
    }

    public function eliminar(Marca $marca): void
    {
        $this->repository->delete($marca);
    }
}
