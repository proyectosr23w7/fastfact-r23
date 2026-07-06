<?php

namespace App\Http\Controllers\Facturacion;

use App\Http\Requests\Facturacion\CloseEventoSignificativoRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\Facturacion\StoreReporteEventoSignificativoRequest;
use App\Http\Requests\Facturacion\StoreEventoSignificativoRequest;
use App\Http\Resources\EventoSignificativoResource;
use App\Models\EventoSignificativo;
use App\Services\Facturacion\EventoSignificativoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventoSignificativoController extends Controller
{
    public function __construct(
        private readonly EventoSignificativoService $service,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Eventos significativos obtenidos correctamente.',
            'data' => EventoSignificativoResource::collection($this->service->listar([
                'sucursal_id' => $request->integer('sucursal_id') ?: null,
                'punto_venta_id' => $request->integer('punto_venta_id') ?: null,
            ], $request->user()))->resolve(),
            'meta' => $this->service->meta($request->user()),
        ]);
    }

    public function store(StoreEventoSignificativoRequest $request): JsonResponse
    {
        $evento = $this->service->activar($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Evento significativo activado correctamente.',
            'data' => EventoSignificativoResource::make($evento)->resolve(),
        ], 201);
    }

    public function close(CloseEventoSignificativoRequest $request, EventoSignificativo $evento): JsonResponse
    {
        $evento = $this->service->cerrar($evento, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Evento significativo cerrado correctamente.',
            'data' => EventoSignificativoResource::make($evento)->resolve(),
        ]);
    }

    public function processRecovery(Request $request, EventoSignificativo $evento): JsonResponse
    {
        $evento = $this->service->procesarRecuperacion($evento, $request->user());
        $message = match ((string) $evento->estado) {
            'concluido' => 'Recuperacion completada. El evento y sus facturas ya quedaron sincronizados con SIAT.',
            'pendiente_validacion_paquetes' => 'El evento ya fue registrado y los paquetes quedaron pendientes de validacion en SIAT. Puedes reintentar mas tarde.',
            default => 'Recuperacion de contingencia procesada correctamente.',
        };

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => EventoSignificativoResource::make($evento)->resolve(),
        ]);
    }

    public function report(StoreReporteEventoSignificativoRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Incidencia SIAT reportada correctamente.',
            'data' => $this->service->reportar($request->validated(), $request->user()),
        ], 201);
    }
}
