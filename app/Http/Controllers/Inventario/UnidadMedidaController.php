<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shared\UpdateEstadoRequest;
use App\Http\Requests\UnidadMedida\StoreUnidadMedidaRequest;
use App\Http\Requests\UnidadMedida\UpdateUnidadMedidaRequest;
use App\Http\Resources\UnidadMedidaResource;
use App\Models\UnidadMedida;
use App\Services\Inventario\UnidadMedidaService;
use Illuminate\Http\JsonResponse;

class UnidadMedidaController extends Controller
{
    public function __construct(
        private readonly UnidadMedidaService $service,
    ) {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Unidades de medida obtenidas correctamente.',
            'data' => UnidadMedidaResource::collection($this->service->listar())->resolve(),
        ]);
    }

    public function store(StoreUnidadMedidaRequest $request): JsonResponse
    {
        $unidadMedida = $this->service->crear($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Unidad de medida registrada correctamente.',
            'data' => UnidadMedidaResource::make($unidadMedida)->resolve(),
        ], 201);
    }

    public function show(UnidadMedida $unidad_medida): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Detalle de la unidad de medida obtenido correctamente.',
            'data' => UnidadMedidaResource::make($unidad_medida)->resolve(),
        ]);
    }

    public function update(UpdateUnidadMedidaRequest $request, UnidadMedida $unidad_medida): JsonResponse
    {
        $unidadMedida = $this->service->actualizar($unidad_medida, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Unidad de medida actualizada correctamente.',
            'data' => UnidadMedidaResource::make($unidadMedida)->resolve(),
        ]);
    }

    public function updateEstado(UpdateEstadoRequest $request, UnidadMedida $unidad_medida): JsonResponse
    {
        $unidadMedida = $this->service->cambiarEstado($unidad_medida, (bool) $request->validated('estado'));

        return response()->json([
            'success' => true,
            'message' => 'Estado de la unidad de medida actualizado correctamente.',
            'data' => UnidadMedidaResource::make($unidadMedida)->resolve(),
        ]);
    }

    public function destroy(UnidadMedida $unidad_medida): JsonResponse
    {
        $this->service->eliminar($unidad_medida);

        return response()->json([
            'success' => true,
            'message' => 'Unidad de medida eliminada correctamente.',
            'data' => null,
        ]);
    }
}
