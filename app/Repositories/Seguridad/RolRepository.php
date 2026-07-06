<?php

namespace App\Repositories\Seguridad;

use App\Models\Role;
use Illuminate\Database\Eloquent\Collection;

class RolRepository
{
    public function allForIndex(): Collection
    {
        return Role::query()
            ->with(['permissions:id,nombre,slug,modulo'])
            ->withCount(['permissions', 'users'])
            ->orderBy('nombre')
            ->get();
    }

    public function create(array $data): Role
    {
        return Role::query()->create($data);
    }

    public function update(Role $role, array $data): Role
    {
        $role->update($data);

        return $this->refresh($role);
    }

    public function syncPermissions(Role $role, array $permissionIds): Role
    {
        $role->permissions()->sync($permissionIds);

        return $this->refresh($role);
    }

    public function refresh(Role $role): Role
    {
        return $role->refresh()->load('permissions:id,nombre,slug,modulo');
    }
}
