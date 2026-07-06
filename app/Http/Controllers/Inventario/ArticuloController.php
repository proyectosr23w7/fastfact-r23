<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use App\Http\Requests\Articulo\StoreArticuloRequest;
use App\Http\Requests\Articulo\UpdateArticuloRequest;
use App\Http\Requests\Shared\UpdateEstadoRequest;
use App\Http\Resources\ArticuloResource;
use App\Models\Articulo;
use App\Services\Inventario\ArticuloService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArticuloController extends Controller
{
    public function __construct(
        private readonly ArticuloService $service,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Articulos obtenidos correctamente.',
            'data' => ArticuloResource::collection(
                $this->service->listar([
                    'search' => $request->string('search')->toString(),
                    'categoria_id' => $request->integer('categoria_id') ?: null,
                    'marca_id' => $request->integer('marca_id') ?: null,
                    'stock' => $request->string('stock')->toString(),
                ]),
            )->resolve(),
            'meta' => $this->service->meta(),
        ]);
    }

    public function store(StoreArticuloRequest $request): JsonResponse
    {
        $articulo = $this->service->crear($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Articulo registrado correctamente.',
            'data' => ArticuloResource::make($articulo)->resolve(),
        ], 201);
    }

    public function show(Articulo $articulo): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Detalle del articulo obtenido correctamente.',
            'data' => ArticuloResource::make($articulo->load([
                'categoria:id,nombre',
                'marca:id,nombre',
                'unidadMedida:id,nombre,abreviatura',
                'precios',
            ]))->resolve(),
        ]);
    }

    public function update(UpdateArticuloRequest $request, Articulo $articulo): JsonResponse
    {
        $articulo = $this->service->actualizar($articulo, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Articulo actualizado correctamente.',
            'data' => ArticuloResource::make($articulo)->resolve(),
        ]);
    }

    public function updateEstado(UpdateEstadoRequest $request, Articulo $articulo): JsonResponse
    {
        $articulo = $this->service->cambiarEstado($articulo, (bool) $request->validated('estado'));

        return response()->json([
            'success' => true,
            'message' => 'Estado del articulo actualizado correctamente.',
            'data' => ArticuloResource::make($articulo)->resolve(),
        ]);
    }

    public function destroy(Articulo $articulo): JsonResponse
    {
        $this->service->eliminar($articulo);

        return response()->json([
            'success' => true,
            'message' => 'Articulo eliminado correctamente.',
            'data' => null,
        ]);
    }
}
