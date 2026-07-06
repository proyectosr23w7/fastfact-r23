<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Configuracion\Sucursal\StoreSucursalRequest;
use App\Http\Requests\Configuracion\Sucursal\UpdateSucursalRequest;
use App\Http\Resources\Configuracion\SucursalResource;
use App\Models\Configuracion\Sucursal;
use App\Services\Configuracion\SucursalService;
use Illuminate\Http\JsonResponse;

class SucursalController extends Controller
{
    public function __construct(
        private readonly SucursalService $service,
    ) {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => SucursalResource::collection($this->service->listar())->resolve(),
        ]);
    }

    public function store(StoreSucursalRequest $request): JsonResponse
    {
        $sucursal = $this->service->crear($request->validated());

        return response()->json([
            'data' => SucursalResource::make($sucursal)->resolve(),
            'message' => 'Sucursal creada correctamente.',
        ], 201);
    }

    public function show(Sucursal $sucursal): JsonResponse
    {
        return response()->json([
            'data' => SucursalResource::make($sucursal)->resolve(),
        ]);
    }

    public function update(UpdateSucursalRequest $request, Sucursal $sucursal): JsonResponse
    {
        $sucursal = $this->service->actualizar($sucursal, $request->validated());

        return response()->json([
            'data' => SucursalResource::make($sucursal)->resolve(),
            'message' => 'Sucursal actualizada correctamente.',
        ]);
    }

    public function destroy(Sucursal $sucursal): JsonResponse
    {
        $this->service->eliminar($sucursal);

        return response()->json([
            'message' => 'Sucursal eliminada correctamente.',
        ]);
    }
}
