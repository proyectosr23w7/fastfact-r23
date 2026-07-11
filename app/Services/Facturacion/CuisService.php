<?php

namespace App\Services\Facturacion;

use App\Models\Configuracion\Configuracion;
use App\Models\User;
use App\Repositories\Facturacion\CuisRepository;
use App\Support\OperationalContextScope;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class CuisService
{
    public function __construct(
        private readonly CuisRepository $repository,
        private readonly SiatClientService $client,
    ) {
    }

    public function listar(array $filters = [], ?User $user = null): Collection
    {
        return $this->repository->allForIndex($filters, $user);
    }

    public function registrar(array $data, int $userId, ?User $user = null)
    {
        OperationalContextScope::authorize(
            $user,
            (int) $data['sucursal_id'],
            (int) $data['punto_venta_id'],
        );

        $configuracion = Configuracion::current();
        if (! $configuracion) {
            abort(422, 'No existe una configuracion general registrada para generar CUIS.');
        }
        $ambiente = $configuracion->ambiente_facturacion ?: 'piloto';

        if (! $configuracion->facturacionSiatActiva()) {
            abort(422, 'La facturacion no esta habilitada en la configuracion del sistema.');
        }

        $this->repository->desactivarVencidos($data['sucursal_id'], $data['punto_venta_id'], $ambiente);

        $vigente = $this->repository->vigente($data['sucursal_id'], $data['punto_venta_id'], $ambiente);

        if ($vigente) {
            return $this->repository->refresh($vigente);
        }

        $response = $this->client->solicitarCuis($data);

        if (blank($response['codigo'] ?? null)) {
            abort(422, $response['message'] ?? 'SIAT no devolvio un CUIS valido.');
        }

        $this->repository->desactivarContexto($data['sucursal_id'], $data['punto_venta_id'], $ambiente);

        return $this->repository->refresh($this->repository->create([
            'codigo' => $response['codigo'],
            'sucursal_id' => $data['sucursal_id'],
            'punto_venta_id' => $data['punto_venta_id'],
            'ambiente_facturacion' => $ambiente,
            'fecha_vigencia' => $response['fecha_vigencia'] ?? null,
            'estado' => true,
            'codigo_respuesta' => $response['code'] ?? null,
            'descripcion_respuesta' => $response['message'] ?? null,
            'user_id' => $userId,
        ]));
    }

    public function generarSiNoExiste(int $sucursalId, int $puntoVentaId, ?int $userId = null, bool $silencioso = false): bool
    {
        try {
            $configuracion = Configuracion::current();

            if (! $configuracion?->facturacionSiatActiva() || blank($configuracion->tokenSiatActivo()) || blank($configuracion->codigo_sistema)) {
                return false;
            }

            $ambiente = $configuracion->ambiente_facturacion ?: 'piloto';
            $this->repository->desactivarVencidos($sucursalId, $puntoVentaId, $ambiente);

            if ($this->repository->vigente($sucursalId, $puntoVentaId, $ambiente)) {
                return true;
            }

            $this->registrar([
                'sucursal_id' => $sucursalId,
                'punto_venta_id' => $puntoVentaId,
            ], $userId ?? 1);

            return true;
        } catch (\Throwable $exception) {
            if (! $silencioso) {
                throw $exception;
            }

            Log::warning('No se pudo generar CUIS automatico.', [
                'sucursal_id' => $sucursalId,
                'punto_venta_id' => $puntoVentaId,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    public function meta(?User $user = null): array
    {
        return [
            'sucursales' => OperationalContextScope::sucursalesQuery($user)->get(['id', 'codigo', 'nombre']),
            'puntos_venta' => OperationalContextScope::puntosVentaQuery($user)->get(['id', 'sucursal_id', 'codigo', 'nombre']),
            'siat' => $this->client->profile(),
        ];
    }
}
