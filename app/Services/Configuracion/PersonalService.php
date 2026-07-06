<?php

namespace App\Services\Configuracion;

use App\Models\Configuracion\Personal;
use App\Repositories\Configuracion\PersonalRepository;
use Illuminate\Database\Eloquent\Collection;

class PersonalService
{
    public function __construct(
        private readonly PersonalRepository $repository,
    ) {
    }

    public function listar(): Collection
    {
        return $this->repository->allForIndex();
    }

    public function crear(array $data): Personal
    {
        return $this->repository->create($data);
    }

    public function actualizar(Personal $personal, array $data): Personal
    {
        return $this->repository->update($personal, $data);
    }

    public function eliminar(Personal $personal): void
    {
        $this->repository->delete($personal);
    }
}
