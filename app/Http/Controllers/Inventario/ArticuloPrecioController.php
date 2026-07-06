<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use App\Http\Requests\ArticuloPrecio\StoreArticuloPrecioRequest;
use App\Http\Requests\ArticuloPrecio\UpdateArticuloPrecioRequest;
use App\Http\Requests\Shared\UpdateEstadoRequest;
use App\Http\Resources\ArticuloPrecioResource;
use App\Models\ArticuloPrecio;
use App\Services\Inventario\ArticuloPrecioService;
use Illuminate\Http\JsonResponse;

class ArticuloPrecioController extends Controller
{
    public function __construct(
        private readonly ArticuloPrecioService $service,
    ) {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Precios de articulos obtenidos correctamente.',
            'data' => ArticuloPrecioResource::collection($this->service->listar())->resolve(),
        ]);
    }

    public function store(StoreArticuloPrecioRequest $request): JsonResponse
    {
        $articuloPrecio = $this->service->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Precio del articulo registrado correctamente.',
            'data' => ArticuloPrecioResource::make($articuloPrecio)->resolve(),
        ], 201);
    }

    public function show(ArticuloPrecio $articulo_precio): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Detalle del precio del articulo obtenido correctamente.',
            'data' => ArticuloPrecioResource::make($articulo_precio)->resolve(),
        ]);
    }

    public function update(UpdateArticuloPrecioRequest $request, ArticuloPrecio $articulo_precio): JsonResponse
    {
        $articuloPrecio = $this->service->update($articulo_precio, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Precio del articulo actualizado correctamente.',
            'data' => ArticuloPrecioResource::make($articuloPrecio)->resolve(),
        ]);
    }

    public function updateEstado(UpdateEstadoRequest $request, ArticuloPrecio $articulo_precio): JsonResponse
    {
        $articuloPrecio = $this->service->update($articulo_precio, [
            'estado' => (bool) $request->validated('estado'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Estado del precio del articulo actualizado correctamente.',
            'data' => ArticuloPrecioResource::make($articuloPrecio)->resolve(),
        ]);
    }

    public function destroy(ArticuloPrecio $articulo_precio): JsonResponse
    {
        $this->service->delete($articulo_precio);

        return response()->json([
            'success' => true,
            'message' => 'Precio del articulo eliminado correctamente.',
            'data' => null,
        ]);
    }
}
