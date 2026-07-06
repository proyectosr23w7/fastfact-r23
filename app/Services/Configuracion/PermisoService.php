<?php

namespace App\Services\Configuracion;

use App\Models\Configuracion\Permiso;
use App\Repositories\Configuracion\PermisoRepository;
use Illuminate\Database\Eloquent\Collection;

class PermisoService
{
    public function __construct(
        private readonly PermisoRepository $repository,
    ) {
    }

    public function listar(): Collection
    {
        return $this->repository->allForIndex();
    }

    public function crear(array $data): Permiso
    {
        return $this->repository->create($data);
    }

    public function actualizar(Permiso $permiso, array $data): Permiso
    {
        return $this->repository->update($permiso, $data);
    }

    public function eliminar(Permiso $permiso): void
    {
        $this->repository->delete($permiso);
    }
}
