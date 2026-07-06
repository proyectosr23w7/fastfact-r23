<?php

namespace App\Repositories\Configuracion;

use App\Models\Configuracion\Role;
use Illuminate\Database\Eloquent\Collection;

class RoleRepository
{
    public function allForIndex(): Collection
    {
        return Role::query()
            ->withCount('permisos')
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

        return $role->refresh();
    }

    public function delete(Role $role): void
    {
        $role->delete();
    }
}
