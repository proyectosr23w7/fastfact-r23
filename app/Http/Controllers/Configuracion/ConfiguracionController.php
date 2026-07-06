<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Configuracion\Configuracion\StoreConfiguracionRequest;
use App\Http\Requests\Configuracion\Configuracion\UpdateConfiguracionRequest;
use App\Http\Resources\Configuracion\ConfiguracionResource;
use App\Models\Configuracion\Configuracion;
use App\Services\Configuracion\ConfiguracionService;
use Illuminate\Http\JsonResponse;

class ConfiguracionController extends Controller
{
    public function __construct(
        private readonly ConfiguracionService $service,
    ) {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => ConfiguracionResource::collection($this->service->listar())->resolve(),
        ]);
    }

    public function store(StoreConfiguracionRequest $request): JsonResponse
    {
        $configuracion = $this->service->crear($request->validated());

        return response()->json([
            'data' => ConfiguracionResource::make($configuracion)->resolve(),
            'message' => 'Configuracion registrada correctamente.',
        ], 201);
    }

    public function show(Configuracion $configuracion): JsonResponse
    {
        return response()->json([
            'data' => ConfiguracionResource::make($configuracion)->resolve(),
        ]);
    }

    public function update(UpdateConfiguracionRequest $request, Configuracion $configuracion): JsonResponse
    {
        $configuracion = $this->service->actualizar($configuracion, $request->validated());

        return response()->json([
            'data' => ConfiguracionResource::make($configuracion)->resolve(),
            'message' => 'Configuracion actualizada correctamente.',
        ]);
    }

    public function destroy(Configuracion $configuracion): JsonResponse
    {
        $this->service->eliminar($configuracion);

        return response()->json([
            'message' => 'Configuracion eliminada correctamente.',
        ]);
    }
}
