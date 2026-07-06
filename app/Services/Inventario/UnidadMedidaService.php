<?php

namespace App\Services\Inventario;

use App\Models\UnidadMedida;
use App\Repositories\Inventario\UnidadMedidaRepository;
use Illuminate\Database\Eloquent\Collection;

class UnidadMedidaService
{
    public function __construct(
        private readonly UnidadMedidaRepository $repository,
    ) {
    }

    public function listar(): Collection
    {
        return $this->repository->allForIndex();
    }

    public function crear(array $data): UnidadMedida
    {
        $data['estado'] = $data['estado'] ?? true;

        return $this->repository->create($data);
    }

    public function actualizar(UnidadMedida $unidadMedida, array $data): UnidadMedida
    {
        return $this->repository->update($unidadMedida, $data);
    }

    public function cambiarEstado(UnidadMedida $unidadMedida, bool $estado): UnidadMedida
    {
        return $this->repository->update($unidadMedida, ['estado' => $estado]);
    }

    public function eliminar(UnidadMedida $unidadMedida): void
    {
        $this->repository->delete($unidadMedida);
    }
}
