<?php

namespace App\Actions\Facturacion;

use App\Repositories\Facturacion\CufdRepository;

class ObtenerCufdVigenteAction
{
    public function __construct(
        private readonly CufdRepository $repository,
    ) {
    }

    public function __invoke(int $sucursalId, int $puntoVentaId, ?string $ambiente = null)
    {
        return $this->repository->vigente($sucursalId, $puntoVentaId, $ambiente);
    }
}
