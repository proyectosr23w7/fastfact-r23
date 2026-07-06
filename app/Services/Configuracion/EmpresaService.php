<?php

namespace App\Services\Configuracion;

use App\Models\Configuracion\Empresa;
use App\Repositories\Configuracion\PuntoVentaRepository;
use App\Repositories\Configuracion\EmpresaRepository;
use App\Repositories\Configuracion\SucursalRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Illuminate\Validation\ValidationException;

class EmpresaService
{
    public function __construct(
        private readonly EmpresaRepository $repository,
        private readonly SucursalRepository $sucursalRepository,
        private readonly PuntoVentaRepository $puntoVentaRepository,
    ) {
    }

    public function listar(): Collection
    {
        return $this->repository->allForIndex();
    }

    public function crear(array $data): Empresa
    {
        if ($this->repository->exists()) {
            throw ValidationException::withMessages([
                'nombre_empresa' => 'Solo puede existir una empresa por instalacion.',
            ]);
        }

        $logo = $this->storeLogo($data['logo'] ?? null);

        if ($logo !== null) {
            $data['logo'] = $logo;
        }

        try {
            return DB::transaction(function () use ($data): Empresa {
                $empresa = $this->repository->create($data);

                $sucursal = $this->sucursalRepository->create([
                    'codigo' => 0,
                    'nombre' => 'CASA MATRIZ',
                    'direccion' => $empresa->direccion,
                    'telefono' => $empresa->telefono,
                    'estado' => true,
                ]);

                $this->puntoVentaRepository->createDefaultForSucursal($sucursal->id, $sucursal->nombre);

                return $empresa;
            });
        } catch (\Throwable $exception) {
            if ($logo !== null) {
                Storage::disk('public')->delete($logo);
            }

            throw $exception;
        }
    }

    public function actualizar(Empresa $empresa, array $data): Empresa
    {
        $previousLogo = $empresa->storedLogoPath();
        $logo = $this->storeLogo($data['logo'] ?? null);

        if ($logo !== null) {
            $data['logo'] = $logo;
        } elseif (($data['logo'] ?? null) instanceof UploadedFile) {
            unset($data['logo']);
        }

        try {
            $empresa = $this->repository->update($empresa, $data);
        } catch (\Throwable $exception) {
            if ($logo !== null) {
                Storage::disk('public')->delete($logo);
            }

            throw $exception;
        }

        if ($logo !== null && $previousLogo !== null && $previousLogo !== $logo) {
            Storage::disk('public')->delete($previousLogo);
        }

        return $empresa;
    }

    public function eliminar(Empresa $empresa): void
    {
        throw ValidationException::withMessages([
            'empresa' => 'La empresa principal no puede eliminarse desde este modulo.',
        ]);
    }

    private function storeLogo(mixed $logo): ?string
    {
        if (! $logo instanceof UploadedFile) {
            return null;
        }

        $path = $logo->store('empresas/logos', 'public');

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('No se pudo almacenar el logo de la empresa.');
        }

        return $path;
    }
}
