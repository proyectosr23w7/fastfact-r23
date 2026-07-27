<?php

namespace App\Services\Facturacion;

use App\Actions\Facturacion\ObtenerCuisVigenteAction;
use App\Models\Configuracion\Configuracion;
use App\Models\Cufd;
use App\Models\User;
use App\Repositories\Facturacion\CufdRepository;
use App\Support\OperationalContextScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class CufdService
{
    public function __construct(
        private readonly CufdRepository $repository,
        private readonly SiatClientService $client,
        private readonly ObtenerCuisVigenteAction $obtenerCuisVigente,
    ) {}

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
            abort(422, 'No existe una configuracion general registrada para generar CUFD.');
        }
        $ambiente = $configuracion->ambiente_facturacion ?: 'piloto';
        $forzarNuevo = (bool) ($data['forzar_nuevo'] ?? false);

        if (! $configuracion->facturacionSiatActiva()) {
            abort(422, 'La facturacion no esta habilitada en la configuracion del sistema.');
        }

        if (($this->obtenerCuisVigente)($data['sucursal_id'], $data['punto_venta_id'], $ambiente) === null) {
            abort(422, 'Debe existir un CUIS vigente antes de registrar un CUFD.');
        }

        $this->repository->desactivarVencidos($data['sucursal_id'], $data['punto_venta_id'], $ambiente);
        $this->repository->desactivarNoUsables($data['sucursal_id'], $data['punto_venta_id'], $ambiente);
        $vigenteActual = $this->repository->vigente($data['sucursal_id'], $data['punto_venta_id'], $ambiente);
        $vigenteDelDia = $this->repository->vigenteDelDia($data['sucursal_id'], $data['punto_venta_id'], $ambiente);

        if ($vigenteDelDia && ! $forzarNuevo) {
            return $this->repository->refresh($vigenteDelDia);
        }

        if (! empty($data['codigo'])) {
            $this->repository->desactivarContexto($data['sucursal_id'], $data['punto_venta_id'], $ambiente);

            return $this->repository->refresh($this->repository->create([
                'codigo' => $data['codigo'],
                'codigo_control' => $data['codigo_control'] ?? null,
                'direccion' => $data['direccion'] ?? null,
                'sucursal_id' => $data['sucursal_id'],
                'punto_venta_id' => $data['punto_venta_id'],
                'ambiente_facturacion' => $ambiente,
                'fecha_vigencia' => $data['fecha_vigencia'] ?? null,
                'estado' => true,
                'codigo_respuesta' => 'MANUAL',
                'descripcion_respuesta' => 'CUFD registrado manualmente.',
                'user_id' => $userId,
            ]));
        }

        $response = $this->client->solicitarCufd($data);

        if (! $this->responseContainsValidCufd($response)) {
            Log::warning('SIAT no devolvio un CUFD usable.', [
                'sucursal_id' => $data['sucursal_id'],
                'punto_venta_id' => $data['punto_venta_id'],
                'ambiente_facturacion' => $ambiente,
                'response' => $response,
            ]);

            abort(422, $this->buildSiatFailureMessage($response, $vigenteActual));
        }

        $this->repository->desactivarContexto($data['sucursal_id'], $data['punto_venta_id'], $ambiente);

        return $this->repository->refresh($this->repository->create([
            'codigo' => $response['codigo'],
            'codigo_control' => $response['codigo_control'] ?? null,
            'direccion' => $response['direccion'] ?? null,
            'sucursal_id' => $data['sucursal_id'],
            'punto_venta_id' => $data['punto_venta_id'],
            'ambiente_facturacion' => $ambiente,
            'fecha_vigencia' => $response['fecha_vigencia'] ?? $data['fecha_vigencia'] ?? now()->addDay(),
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
            $this->repository->desactivarNoUsables($sucursalId, $puntoVentaId, $ambiente);

            if ($this->repository->vigenteDelDia($sucursalId, $puntoVentaId, $ambiente)) {
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

            Log::warning('No se pudo generar CUFD automatico.', [
                'sucursal_id' => $sucursalId,
                'punto_venta_id' => $puntoVentaId,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    public function obtenerVigenteORegistrar(int $sucursalId, int $puntoVentaId, int $userId, ?User $user = null): Cufd
    {
        $configuracion = Configuracion::current();

        if (! $configuracion) {
            abort(422, 'No existe una configuracion general registrada para generar CUFD.');
        }

        $ambiente = $configuracion->ambiente_facturacion ?: 'piloto';

        $this->repository->desactivarVencidos($sucursalId, $puntoVentaId, $ambiente);
        $this->repository->desactivarNoUsables($sucursalId, $puntoVentaId, $ambiente);

        $vigente = $this->repository->vigente($sucursalId, $puntoVentaId, $ambiente);

        if ($vigente) {
            return $this->repository->refresh($vigente);
        }

        return $this->registrar([
            'sucursal_id' => $sucursalId,
            'punto_venta_id' => $puntoVentaId,
        ], $userId, $user);
    }

    private function responseContainsValidCufd(array $response): bool
    {
        if (($response['success'] ?? false) !== true) {
            return false;
        }

        if (blank($response['codigo'] ?? null) || blank($response['codigo_control'] ?? null)) {
            return false;
        }

        try {
            return filled($response['fecha_vigencia'] ?? null)
                && Carbon::parse((string) $response['fecha_vigencia']) instanceof \Carbon\CarbonInterface;
        } catch (\Throwable) {
            return false;
        }
    }

    private function buildSiatFailureMessage(array $response, ?Cufd $vigenteActual): string
    {
        $message = trim((string) ($response['message'] ?? 'SIAT no devolvio un CUFD valido para el contexto seleccionado.'));
        $contextMessage = 'No se pudo generar un nuevo CUFD porque SIAT devolvio una observacion externa.';

        if (! $vigenteActual) {
            return "{$contextMessage} {$message}";
        }

        $vigencia = optional($vigenteActual->fecha_vigencia)?->format('d/m/Y H:i:s') ?? 'sin fecha de vigencia registrada';

        return "{$contextMessage} {$message} Se mantiene activo el CUFD vigente actual hasta {$vigencia}.";
    }

    public function meta(?User $user = null): array
    {
        $sucursales = OperationalContextScope::sucursalesQuery($user);
        $puntosVenta = OperationalContextScope::puntosVentaQuery($user);

        return [
            'sucursales' => $sucursales->get(['id', 'codigo', 'nombre']),
            'puntos_venta' => $puntosVenta->get(['id', 'sucursal_id', 'codigo', 'nombre']),
            'siat' => $this->client->profile(),
            'ambientes' => $this->ambientes(),
            'puede_gestionar' => (bool) ($user?->hasPermission('facturacion.siat.sync') ?? false),
        ];
    }

    private function ambientes(): array
    {
        return [
            ['value' => 'piloto', 'label' => 'Piloto'],
            ['value' => 'produccion', 'label' => 'Produccion'],
        ];
    }
}
