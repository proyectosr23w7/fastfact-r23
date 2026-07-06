<?php

namespace App\Http\Controllers\Seguridad;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rol\AssignPermissionsRequest;
use App\Http\Requests\Rol\StoreRolRequest;
use App\Http\Requests\Rol\UpdateRolEstadoRequest;
use App\Http\Requests\Rol\UpdateRolRequest;
use App\Http\Resources\PermisoResource;
use App\Http\Resources\RolResource;
use App\Models\Permission;
use App\Models\Role;
use App\Services\Seguridad\RolService;
use Illuminate\Http\JsonResponse;

class RolController extends Controller
{
    public function __construct(
        private readonly RolService $service,
    ) {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Roles obtenidos correctamente.',
            'data' => RolResource::collection($this->service->listar())->resolve(),
            'meta' => [
                'permissions' => PermisoResource::collection(Permission::query()->where('estado', true)->orderBy('modulo')->orderBy('nombre')->get())->resolve(),
            ],
        ]);
    }

    public function store(StoreRolRequest $request): JsonResponse
    {
        $rol = $this->service->crear($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Rol registrado correctamente.',
            'data' => RolResource::make($rol)->resolve(),
        ], 201);
    }

    public function show(Role $rol): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Detalle del rol obtenido correctamente.',
            'data' => RolResource::make($rol->load('permissions'))->resolve(),
        ]);
    }

    public function update(UpdateRolRequest $request, Role $rol): JsonResponse
    {
        $rol = $this->service->actualizar($rol, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Rol actualizado correctamente.',
            'data' => RolResource::make($rol)->resolve(),
        ]);
    }

    public function updateEstado(UpdateRolEstadoRequest $request, Role $rol): JsonResponse
    {
        $rol = $this->service->cambiarEstado($rol, (bool) $request->validated('estado'));

        return response()->json([
            'success' => true,
            'message' => 'Estado del rol actualizado correctamente.',
            'data' => RolResource::make($rol)->resolve(),
        ]);
    }

    public function assignPermissions(AssignPermissionsRequest $request, Role $rol): JsonResponse
    {
        $rol = $this->service->asignarPermisos($rol, $request->validated('permission_ids'));

        return response()->json([
            'success' => true,
            'message' => 'Permisos asignados correctamente.',
            'data' => RolResource::make($rol)->resolve(),
        ]);
    }
}
