<?php

namespace App\Repositories\Configuracion;

use App\Models\Configuracion\Puesto;
use Illuminate\Database\Eloquent\Collection;

class PuestoRepository
{
    public function allForIndex(): Collection
    {
        return Puesto::query()
            ->withCount('personal')
            ->orderBy('nombre')
            ->get();
    }

    public function create(array $data): Puesto
    {
        return Puesto::query()->create($data);
    }

    public function update(Puesto $puesto, array $data): Puesto
    {
        $puesto->update($data);

        return $puesto->refresh();
    }

    public function delete(Puesto $puesto): void
    {
        $puesto->delete();
    }
}
