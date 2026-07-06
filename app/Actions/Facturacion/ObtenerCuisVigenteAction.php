<?php

namespace App\Actions\Facturacion;

use App\Repositories\Facturacion\CuisRepository;

class ObtenerCuisVigenteAction
{
    public function __construct(
        private readonly CuisRepository $repository,
    ) {
    }

    public function __invoke(int $sucursalId, int $puntoVentaId, ?string $ambiente = null)
    {
        return $this->repository->vigente($sucursalId, $puntoVentaId, $ambiente);
    }
}
