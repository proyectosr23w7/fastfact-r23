<?php

namespace App\Http\Controllers\Facturacion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Facturacion\SyncCatalogosRequest;
use App\Http\Requests\Facturacion\UpdateMetodoPagoOperativoRequest;
use App\Http\Requests\Shared\UpdateEstadoRequest;
use App\Http\Resources\SiatSyncResource;
use App\Models\SinMetodoPago;
use App\Models\SinUnidadMedida;
use App\Services\Facturacion\SiatSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiatSyncController extends Controller
{
    public function __construct(
        private readonly SiatSyncService $service,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Historial de sincronizaciones SIAT obtenido correctamente.',
            'data' => SiatSyncResource::collection($this->service->listar($request->user()))->resolve(),
            'meta' => $this->service->meta($request->user()),
        ]);
    }

    public function sincronizar(SyncCatalogosRequest $request): JsonResponse
    {
        $result = $this->service->sincronizarCatalogos($request->validated(), $request->user());

        return response()->json([
            'success' => (bool) ($result['response']['success'] ?? false),
            'message' => $result['response']['message'] ?? 'Sincronizacion SIAT ejecutada con observaciones.',
            'data' => SiatSyncResource::collection($result['logs'])->resolve(),
            'meta' => [
                'response' => $result['response'],
            ],
        ]);
    }

    public function updateMetodoPagoEstado(UpdateEstadoRequest $request, SinMetodoPago $metodo_pago): JsonResponse
    {
        $this->service->autorizarGestionCatalogos($request->user());
        $metodo = $this->service->cambiarEstadoMetodoPago($metodo_pago, (bool) $request->validated('estado'));

        return response()->json([
            'success' => true,
            'message' => 'Disponibilidad operativa del metodo de pago actualizada correctamente.',
            'data' => $metodo,
        ]);
    }

    public function updateMetodoPagoOperativo(UpdateMetodoPagoOperativoRequest $request, SinMetodoPago $metodo_pago): JsonResponse
    {
        $this->service->autorizarGestionCatalogos($request->user());
        $metodo = $this->service->actualizarMetodoPagoOperativo($metodo_pago, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Preferencias operativas del metodo de pago actualizadas correctamente.',
            'data' => $metodo,
        ]);
    }

    public function updateUnidadMedidaEstado(UpdateEstadoRequest $request, SinUnidadMedida $unidad_medida): JsonResponse
    {
        $this->service->autorizarGestionCatalogos($request->user());
        $unidad = $this->service->cambiarEstadoUnidadMedida($unidad_medida, (bool) $request->validated('estado'));

        return response()->json([
            'success' => true,
            'message' => 'Disponibilidad operativa de la unidad de medida SIAT actualizada correctamente.',
            'data' => $unidad,
        ]);
    }
}
