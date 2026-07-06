<?php

namespace App\Repositories\Seguridad;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Collection;

class PermisoRepository
{
    public function allForIndex(): Collection
    {
        return Permission::query()
            ->withCount('roles')
            ->orderBy('modulo')
            ->orderBy('nombre')
            ->get();
    }

    public function create(array $data): Permission
    {
        return Permission::query()->create($data);
    }

    public function update(Permission $permission, array $data): Permission
    {
        $permission->update($data);

        return $permission->refresh();
    }
}
