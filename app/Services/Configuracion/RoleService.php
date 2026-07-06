<?php

namespace App\Services\Configuracion;

use App\Models\Configuracion\Role;
use App\Repositories\Configuracion\RoleRepository;
use Illuminate\Database\Eloquent\Collection;

class RoleService
{
    public function __construct(
        private readonly RoleRepository $repository,
    ) {
    }

    public function listar(): Collection
    {
        return $this->repository->allForIndex();
    }

    public function crear(array $data): Role
    {
        return $this->repository->create($data);
    }

    public function actualizar(Role $role, array $data): Role
    {
        return $this->repository->update($role, $data);
    }

    public function eliminar(Role $role): void
    {
        $this->repository->delete($role);
    }
}
