<?php

namespace App\Repositories\Configuracion;

use App\Models\Configuracion\Empresa;
use Illuminate\Database\Eloquent\Collection;

class EmpresaRepository
{
    public function exists(): bool
    {
        return Empresa::query()->exists();
    }

    public function allForIndex(): Collection
    {
        return Empresa::query()
            ->orderBy('nombre_empresa')
            ->get();
    }

    public function create(array $data): Empresa
    {
        return Empresa::query()->create($data);
    }

    public function update(Empresa $empresa, array $data): Empresa
    {
        $empresa->update($data);

        return $empresa->refresh();
    }

    public function delete(Empresa $empresa): void
    {
        $empresa->delete();
    }
}
