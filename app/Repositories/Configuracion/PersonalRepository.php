<?php

namespace App\Repositories\Configuracion;

use App\Models\Configuracion\Personal;
use Illuminate\Database\Eloquent\Collection;

class PersonalRepository
{
    public function allForIndex(): Collection
    {
        return Personal::query()
            ->with('puesto:id,nombre')
            ->orderBy('nombre_completo')
            ->get();
    }

    public function create(array $data): Personal
    {
        return Personal::query()->create($data);
    }

    public function update(Personal $personal, array $data): Personal
    {
        $personal->update($data);

        return $personal->refresh()->load('puesto:id,nombre');
    }

    public function delete(Personal $personal): void
    {
        $personal->delete();
    }
}
