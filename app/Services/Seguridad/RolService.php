<?php

namespace App\Services\Seguridad;

use App\Enums\RolSistemaEnum;
use App\Models\Role;
use App\Repositories\Seguridad\RolRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class RolService
{
    public function __construct(
        private readonly RolRepository $repository,
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

    public function cambiarEstado(Role $role, bool $estado): Role
    {
        if ($role->slug === RolSistemaEnum::SUPERADMIN->value && ! $estado) {
            throw ValidationException::withMessages([
                'estado' => 'El rol superadmin no puede deshabilitarse.',
            ]);
        }

        return $this->repository->update($role, ['estado' => $estado]);
    }

    public function asignarPermisos(Role $role, array $permissionIds): Role
    {
        return $this->repository->syncPermissions($role, $permissionIds);
    }
}
