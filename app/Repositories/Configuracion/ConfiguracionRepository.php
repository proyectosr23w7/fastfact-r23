<?php

namespace App\Repositories\Configuracion;

use App\Models\Configuracion\Configuracion;
use Illuminate\Database\Eloquent\Collection;

class ConfiguracionRepository
{
    public function allForIndex(): Collection
    {
        $configuracion = Configuracion::ensureCurrent();

        return new Collection([$configuracion]);
    }

    public function singleton(): Configuracion
    {
        return Configuracion::ensureCurrent();
    }

    public function create(array $data): Configuracion
    {
        return Configuracion::query()->create($data);
    }

    public function update(Configuracion $configuracion, array $data): Configuracion
    {
        $configuracion->update($data);

        return $configuracion->refresh();
    }

    public function delete(Configuracion $configuracion): void
    {
        $configuracion->delete();
    }
}
