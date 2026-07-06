<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Configuracion\PuntoVenta\StorePuntoVentaRequest;
use App\Http\Requests\Configuracion\PuntoVenta\UpdatePuntoVentaRequest;
use App\Http\Resources\Configuracion\PuntoVentaResource;
use App\Http\Resources\Configuracion\SucursalResource;
use App\Models\Configuracion\PuntoVenta;
use App\Models\Configuracion\Sucursal;
use App\Services\Configuracion\PuntoVentaService;
use Illuminate\Http\JsonResponse;

class PuntoVentaController extends Controller
{
    public function __construct(
        private readonly PuntoVentaService $service,
    ) {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => PuntoVentaResource::collection($this->service->listar())->resolve(),
            'meta' => [
                'sucursales' => SucursalResource::collection(Sucursal::query()->orderBy('codigo')->get())->resolve(),
            ],
        ]);
    }

    public function store(StorePuntoVentaRequest $request): JsonResponse
    {
        $puntoVenta = $this->service->crear($request->validated());

        return response()->json([
            'data' => PuntoVentaResource::make($puntoVenta->load(['sucursal:id,codigo,nombre', 'cuisVigente']))->resolve(),
            'message' => 'Punto de venta registrado correctamente.',
        ], 201);
    }

    public function show(PuntoVenta $puntoVenta): JsonResponse
    {
        return response()->json([
            'data' => PuntoVentaResource::make($puntoVenta->load(['sucursal:id,codigo,nombre', 'cuisVigente']))->resolve(),
        ]);
    }

    public function update(UpdatePuntoVentaRequest $request, PuntoVenta $puntoVenta): JsonResponse
    {
        $puntoVenta = $this->service->actualizar($puntoVenta, $request->validated());

        return response()->json([
            'data' => PuntoVentaResource::make($puntoVenta)->resolve(),
            'message' => 'Punto de venta actualizado correctamente.',
        ]);
    }

    public function destroy(PuntoVenta $puntoVenta): JsonResponse
    {
        $this->service->eliminar($puntoVenta);

        return response()->json([
            'message' => 'Punto de venta eliminado correctamente.',
        ]);
    }
}
