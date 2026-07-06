<?php

namespace App\Services\Configuracion;

use App\Models\Configuracion\PuntoVenta;
use App\Repositories\Configuracion\PuntoVentaRepository;
use App\Services\Facturacion\CuisService;
use Illuminate\Database\Eloquent\Collection;

class PuntoVentaService
{
    public function __construct(
        private readonly PuntoVentaRepository $repository,
        private readonly CuisService $cuisService,
    ) {
    }

    public function listar(): Collection
    {
        return $this->repository->allForIndex();
    }

    public function crear(array $data): PuntoVenta
    {
        $puntoVenta = $this->repository->create($data);

        // La creacion de punto de venta no debe fallar si SIAT aun no tiene credenciales;
        // se intenta generar CUIS de forma segura y se registra advertencia si no se puede.
        $this->cuisService->generarSiNoExiste(
            (int) $puntoVenta->sucursal_id,
            (int) $puntoVenta->id,
            auth()->id(),
            silencioso: true,
        );

        return $puntoVenta;
    }

    public function actualizar(PuntoVenta $puntoVenta, array $data): PuntoVenta
    {
        return $this->repository->update($puntoVenta, $data);
    }

    public function eliminar(PuntoVenta $puntoVenta): void
    {
        $this->repository->delete($puntoVenta);
    }
}
