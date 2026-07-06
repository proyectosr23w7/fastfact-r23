<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use App\Http\Requests\Categoria\StoreCategoriaRequest;
use App\Http\Requests\Categoria\UpdateCategoriaRequest;
use App\Http\Requests\Shared\UpdateEstadoRequest;
use App\Http\Resources\CategoriaResource;
use App\Models\Categoria;
use App\Services\Inventario\CategoriaService;
use Illuminate\Http\JsonResponse;

class CategoriaController extends Controller
{
    public function __construct(
        private readonly CategoriaService $service,
    ) {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Categorias obtenidas correctamente.',
            'data' => CategoriaResource::collection($this->service->listar())->resolve(),
        ]);
    }

    public function store(StoreCategoriaRequest $request): JsonResponse
    {
        $categoria = $this->service->crear($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Categoria registrada correctamente.',
            'data' => CategoriaResource::make($categoria)->resolve(),
        ], 201);
    }

    public function show(Categoria $categoria): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Detalle de la categoria obtenido correctamente.',
            'data' => CategoriaResource::make($categoria)->resolve(),
        ]);
    }

    public function update(UpdateCategoriaRequest $request, Categoria $categoria): JsonResponse
    {
        $categoria = $this->service->actualizar($categoria, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Categoria actualizada correctamente.',
            'data' => CategoriaResource::make($categoria)->resolve(),
        ]);
    }

    public function updateEstado(UpdateEstadoRequest $request, Categoria $categoria): JsonResponse
    {
        $categoria = $this->service->cambiarEstado($categoria, (bool) $request->validated('estado'));

        return response()->json([
            'success' => true,
            'message' => 'Estado de la categoria actualizado correctamente.',
            'data' => CategoriaResource::make($categoria)->resolve(),
        ]);
    }

    public function destroy(Categoria $categoria): JsonResponse
    {
        $this->service->eliminar($categoria);

        return response()->json([
            'success' => true,
            'message' => 'Categoria eliminada correctamente.',
            'data' => null,
        ]);
    }
}
