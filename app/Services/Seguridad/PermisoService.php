<?php

namespace App\Services\Seguridad;

use App\Models\Permission;
use App\Repositories\Seguridad\PermisoRepository;
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

    public function crear(array $data): Permission
    {
        return $this->repository->create($data);
    }

    public function actualizar(Permission $permission, array $data): Permission
    {
        return $this->repository->update($permission, $data);
    }

    public function cambiarEstado(Permission $permission, bool $estado): Permission
    {
        return $this->repository->update($permission, ['estado' => $estado]);
    }
}
