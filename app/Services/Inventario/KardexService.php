<?php

namespace App\Services\Inventario;

use App\Repositories\Inventario\KardexRepository;
use Illuminate\Database\Eloquent\Collection;

class KardexService
{
    public function __construct(
        private readonly KardexRepository $repository,
    ) {
    }

    public function listar(array $filters = []): Collection
    {
        return $this->repository->allForIndex($filters);
    }

    public function ultimoSaldo(int $articuloId, int $sucursalId): float
    {
        return $this->repository->lastBalance($articuloId, $sucursalId);
    }

    public function registrar(array $data)
    {
        return $this->repository->create($data);
    }

}
