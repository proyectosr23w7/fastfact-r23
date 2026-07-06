<?php

namespace App\Http\Controllers\Seguridad;

use App\Enums\ModuloSistemaEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Permiso\StorePermisoRequest;
use App\Http\Requests\Permiso\UpdatePermisoEstadoRequest;
use App\Http\Requests\Permiso\UpdatePermisoRequest;
use App\Http\Resources\PermisoResource;
use App\Models\Permission;
use App\Services\Seguridad\PermisoService;
use Illuminate\Http\JsonResponse;

class PermisoController extends Controller
{
    public function __construct(
        private readonly PermisoService $service,
    ) {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Permisos obtenidos correctamente.',
            'data' => PermisoResource::collection($this->service->listar())->resolve(),
            'meta' => [
                'modules' => ModuloSistemaEnum::options(),
            ],
        ]);
    }

    public function store(StorePermisoRequest $request): JsonResponse
    {
        $permiso = $this->service->crear($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Permiso registrado correctamente.',
            'data' => PermisoResource::make($permiso)->resolve(),
        ], 201);
    }

    public function show(Permission $permiso): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Detalle del permiso obtenido correctamente.',
            'data' => PermisoResource::make($permiso)->resolve(),
        ]);
    }

    public function update(UpdatePermisoRequest $request, Permission $permiso): JsonResponse
    {
        $permiso = $this->service->actualizar($permiso, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Permiso actualizado correctamente.',
            'data' => PermisoResource::make($permiso)->resolve(),
        ]);
    }

    public function updateEstado(UpdatePermisoEstadoRequest $request, Permission $permiso): JsonResponse
    {
        $permiso = $this->service->cambiarEstado($permiso, (bool) $request->validated('estado'));

        return response()->json([
            'success' => true,
            'message' => 'Estado del permiso actualizado correctamente.',
            'data' => PermisoResource::make($permiso)->resolve(),
        ]);
    }
}
