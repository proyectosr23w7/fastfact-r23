<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marca\StoreMarcaRequest;
use App\Http\Requests\Marca\UpdateMarcaRequest;
use App\Http\Requests\Shared\UpdateEstadoRequest;
use App\Http\Resources\MarcaResource;
use App\Models\Marca;
use App\Services\Inventario\MarcaService;
use Illuminate\Http\JsonResponse;

class MarcaController extends Controller
{
    public function __construct(
        private readonly MarcaService $service,
    ) {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Marcas obtenidas correctamente.',
            'data' => MarcaResource::collection($this->service->listar())->resolve(),
        ]);
    }

    public function store(StoreMarcaRequest $request): JsonResponse
    {
        $marca = $this->service->crear($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Marca registrada correctamente.',
            'data' => MarcaResource::make($marca)->resolve(),
        ], 201);
    }

    public function show(Marca $marca): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Detalle de la marca obtenido correctamente.',
            'data' => MarcaResource::make($marca)->resolve(),
        ]);
    }

    public function update(UpdateMarcaRequest $request, Marca $marca): JsonResponse
    {
        $marca = $this->service->actualizar($marca, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Marca actualizada correctamente.',
            'data' => MarcaResource::make($marca)->resolve(),
        ]);
    }

    public function updateEstado(UpdateEstadoRequest $request, Marca $marca): JsonResponse
    {
        $marca = $this->service->cambiarEstado($marca, (bool) $request->validated('estado'));

        return response()->json([
            'success' => true,
            'message' => 'Estado de la marca actualizado correctamente.',
            'data' => MarcaResource::make($marca)->resolve(),
        ]);
    }

    public function destroy(Marca $marca): JsonResponse
    {
        $this->service->eliminar($marca);

        return response()->json([
            'success' => true,
            'message' => 'Marca eliminada correctamente.',
            'data' => null,
        ]);
    }
}
