<?php

namespace App\Services\Facturacion;

use App\Models\Cafc;
use App\Models\Configuracion\Configuracion;
use App\Models\Configuracion\PuntoVenta;
use App\Models\Configuracion\Sucursal;
use App\Models\User;
use App\Repositories\Facturacion\CafcRepository;
use Illuminate\Database\Eloquent\Collection;

class CafcService
{
    public function __construct(
        private readonly CafcRepository $repository,
        private readonly SiatClientService $client,
    ) {
    }

    public function listar(array $filters = [], ?User $user = null): Collection
    {
        $configuracion = Configuracion::current();
        $filters['ambiente_facturacion'] = $filters['ambiente_facturacion'] ?? (string) ($configuracion?->ambiente_facturacion ?: 'piloto');

        return $this->repository->allForIndex($filters);
    }

    public function registrar(array $data, User $user)
    {
        $this->assertCanManage($user);

        $configuracion = Configuracion::current();
        if (! $configuracion) {
            abort(422, 'No existe una configuracion general registrada para administrar CAFC.');
        }

        if (! $configuracion->facturacionSiatActiva()) {
            abort(422, 'La facturacion no esta activa. No corresponde administrar CAFC en este momento.');
        }

        return $this->repository->create([
            'codigo' => trim((string) $data['codigo']),
            'pin' => filled($data['pin'] ?? null) ? trim((string) $data['pin']) : null,
            'descripcion' => filled($data['descripcion'] ?? null) ? trim((string) $data['descripcion']) : null,
            'sucursal_id' => $data['sucursal_id'],
            'punto_venta_id' => $data['punto_venta_id'],
            'ambiente_facturacion' => (string) ($data['ambiente_facturacion'] ?? $configuracion->ambiente_facturacion ?: 'piloto'),
            'fecha_inicio_vigencia' => $data['fecha_inicio_vigencia'] ?? null,
            'fecha_fin_vigencia' => $data['fecha_fin_vigencia'] ?? null,
            'numero_inicial' => $data['numero_inicial'] ?? null,
            'numero_final' => $data['numero_final'] ?? null,
            'estado' => true,
            'observacion' => $data['observacion'] ?? null,
            'user_id' => $user->id,
        ]);
    }

    public function actualizar(Cafc $cafc, array $data, User $user)
    {
        $this->assertCanManage($user);

        return $this->repository->update($cafc, [
            'codigo' => trim((string) $data['codigo']),
            'pin' => filled($data['pin'] ?? null) ? trim((string) $data['pin']) : null,
            'descripcion' => filled($data['descripcion'] ?? null) ? trim((string) $data['descripcion']) : null,
            'sucursal_id' => $data['sucursal_id'],
            'punto_venta_id' => $data['punto_venta_id'],
            'ambiente_facturacion' => (string) $data['ambiente_facturacion'],
            'fecha_inicio_vigencia' => $data['fecha_inicio_vigencia'] ?? null,
            'fecha_fin_vigencia' => $data['fecha_fin_vigencia'] ?? null,
            'numero_inicial' => $data['numero_inicial'] ?? null,
            'numero_final' => $data['numero_final'] ?? null,
            'observacion' => $data['observacion'] ?? null,
            'user_id' => $user->id,
        ]);
    }

    public function actualizarEstado(Cafc $cafc, bool $estado, User $user)
    {
        $this->assertCanManage($user);

        return $this->repository->update($cafc, [
            'estado' => $estado,
            'user_id' => $user->id,
        ]);
    }

    public function meta(?User $user = null): array
    {
        $sucursales = Sucursal::query()->where('estado', true)->orderBy('codigo');
        $puntosVenta = PuntoVenta::query()->where('estado', true)->orderBy('sucursal_id')->orderBy('codigo');

        return [
            'sucursales' => $sucursales->get(['id', 'codigo', 'nombre']),
            'puntos_venta' => $puntosVenta->get(['id', 'sucursal_id', 'codigo', 'nombre']),
            'siat' => $this->client->profile(),
            'ambientes' => [
                ['value' => 'piloto', 'label' => 'Piloto'],
                ['value' => 'produccion', 'label' => 'Produccion'],
            ],
            'puede_gestionar' => (bool) ($user?->canManageAllOperationalContexts() ?? false),
        ];
    }

    private function assertCanManage(User $user): void
    {
        if (! $user->canManageAllOperationalContexts()) {
            abort(403, 'Solo un usuario autorizado puede administrar CAFC.');
        }
    }
}
