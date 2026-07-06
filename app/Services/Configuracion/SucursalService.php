<?php

namespace App\Services\Configuracion;

use App\Models\Configuracion\Sucursal;
use App\Repositories\Configuracion\PuntoVentaRepository;
use App\Repositories\Configuracion\SucursalRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SucursalService
{
    public function __construct(
        private readonly SucursalRepository $repository,
        private readonly PuntoVentaRepository $puntoVentaRepository,
    ) {
    }

    public function listar(): Collection
    {
        return $this->repository->allForIndex();
    }

    public function crear(array $data): Sucursal
    {
        $data = $this->normalizeData($data);
        $this->ensureCodigoDisponible((int) $data['codigo']);

        return DB::transaction(function () use ($data): Sucursal {
            $sucursal = $this->repository->create($data);

            $this->puntoVentaRepository->createDefaultForSucursal($sucursal->id, $sucursal->nombre);

            return $sucursal;
        });
    }

    public function actualizar(Sucursal $sucursal, array $data): Sucursal
    {
        if ((int) $sucursal->codigo === 0 && (int) ($data['codigo'] ?? 0) !== 0) {
            throw ValidationException::withMessages([
                'codigo' => 'Casa Matriz debe conservar el codigo 0.',
            ]);
        }

        $data = $this->normalizeData($data);
        $this->ensureCodigoDisponible((int) $data['codigo'], $sucursal->id);

        return $this->repository->update($sucursal, $data);
    }

    public function eliminar(Sucursal $sucursal): void
    {
        if ((int) $sucursal->codigo === 0) {
            throw ValidationException::withMessages([
                'sucursal' => 'Casa Matriz no puede eliminarse.',
            ]);
        }

        $this->repository->delete($sucursal);
    }

    private function normalizeData(array $data): array
    {
        $codigo = (int) ($data['codigo'] ?? 0);

        $data['codigo'] = $codigo;
        $data['nombre'] = $codigo === 0
            ? 'CASA MATRIZ'
            : 'SUCURSAL '.$codigo;

        return $data;
    }

    private function ensureCodigoDisponible(int $codigo, ?int $ignoreId = null): void
    {
        if (! $this->repository->existsByCodigo($codigo, $ignoreId)) {
            return;
        }

        throw ValidationException::withMessages([
            'codigo' => 'El codigo de sucursal ya se encuentra registrado.',
        ]);
    }
}
