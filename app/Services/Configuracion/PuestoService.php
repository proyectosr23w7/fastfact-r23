<?php

namespace App\Services\Configuracion;

use App\Models\Configuracion\Puesto;
use App\Repositories\Configuracion\PuestoRepository;
use Illuminate\Database\Eloquent\Collection;

class PuestoService
{
    public function __construct(
        private readonly PuestoRepository $repository,
    ) {
    }

    public function listar(): Collection
    {
        return $this->repository->allForIndex();
    }

    public function crear(array $data): Puesto
    {
        return $this->repository->create($data);
    }

    public function actualizar(Puesto $puesto, array $data): Puesto
    {
        return $this->repository->update($puesto, $data);
    }

    public function eliminar(Puesto $puesto): void
    {
        $this->repository->delete($puesto);
    }
}
