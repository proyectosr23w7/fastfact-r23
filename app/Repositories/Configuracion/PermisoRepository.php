<?php

namespace App\Repositories\Configuracion;

use App\Models\Configuracion\Permiso;
use Illuminate\Database\Eloquent\Collection;

class PermisoRepository
{
    public function allForIndex(): Collection
    {
        return Permiso::query()
            ->withCount('roles')
            ->orderBy('modulo')
            ->orderBy('codigo')
            ->get();
    }

    public function create(array $data): Permiso
    {
        return Permiso::query()->create($data);
    }

    public function update(Permiso $permiso, array $data): Permiso
    {
        $permiso->update($data);

        return $permiso->refresh();
    }

    public function delete(Permiso $permiso): void
    {
        $permiso->delete();
    }
}
