<?php

namespace App\Http\Controllers\Seguridad;

use App\Http\Controllers\Controller;
use App\Http\Requests\Usuario\AssignAccessRequest;
use App\Http\Requests\Usuario\AssignRolesRequest;
use App\Http\Requests\Usuario\StoreUsuarioRequest;
use App\Http\Requests\Usuario\UpdateUsuarioEstadoRequest;
use App\Http\Requests\Usuario\UpdateUsuarioRequest;
use App\Http\Resources\Configuracion\PuntoVentaResource;
use App\Http\Resources\Configuracion\SucursalResource;
use App\Http\Resources\PermisoResource;
use App\Http\Resources\RolResource;
use App\Http\Resources\UsuarioResource;
use App\Models\Configuracion\PuntoVenta;
use App\Models\Configuracion\Sucursal;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Seguridad\UsuarioService;
use Illuminate\Http\JsonResponse;

class UsuarioController extends Controller
{
    public function __construct(
        private readonly UsuarioService $service,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Usuarios obtenidos correctamente.',
            'data' => UsuarioResource::collection($this->service->listar())->resolve(),
            'meta' => [
                'roles' => RolResource::collection(
                    Role::query()->with('permissions')->where('estado', true)->orderBy('nombre')->get(),
                )->resolve(),
                'permissions' => PermisoResource::collection(
                    Permission::query()->where('estado', true)->orderBy('modulo')->orderBy('nombre')->get(),
                )->resolve(),
                'sucursales' => SucursalResource::collection(
                    Sucursal::query()->where('estado', true)->orderBy('codigo')->get(),
                )->resolve(),
                'puntos_venta' => PuntoVentaResource::collection(
                    PuntoVenta::query()
                        ->with('sucursal:id,codigo,nombre')
                        ->where('estado', true)
                        ->orderBy('sucursal_id')
                        ->orderBy('codigo')
                        ->get(),
                )->resolve(),
            ],
        ]);
    }

    public function store(StoreUsuarioRequest $request): JsonResponse
    {
        $usuario = $this->service->crear($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Usuario registrado correctamente.',
            'data' => UsuarioResource::make($usuario)->resolve(),
        ], 201);
    }

    public function show(User $usuario): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Detalle del usuario obtenido correctamente.',
            'data' => UsuarioResource::make($usuario->load(['roles.permissions']))->resolve(),
        ]);
    }

    public function update(UpdateUsuarioRequest $request, User $usuario): JsonResponse
    {
        $usuario = $this->service->actualizar($usuario, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Usuario actualizado correctamente.',
            'data' => UsuarioResource::make($usuario)->resolve(),
        ]);
    }

    public function updateEstado(UpdateUsuarioEstadoRequest $request, User $usuario): JsonResponse
    {
        $usuario = $this->service->cambiarEstado(
            $usuario,
            (bool) $request->validated('estado'),
            $request->user()?->id,
        );

        return response()->json([
            'success' => true,
            'message' => 'Estado del usuario actualizado correctamente.',
            'data' => UsuarioResource::make($usuario)->resolve(),
        ]);
    }

    public function assignRoles(AssignRolesRequest $request, User $usuario): JsonResponse
    {
        $usuario = $this->service->asignarRoles($usuario, $request->validated('role_ids'));

        return response()->json([
            'success' => true,
            'message' => 'Roles asignados correctamente.',
            'data' => UsuarioResource::make($usuario)->resolve(),
        ]);
    }

    public function assignAccess(AssignAccessRequest $request, User $usuario): JsonResponse
    {
        $usuario = $this->service->asignarAcceso($usuario, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Acceso del usuario actualizado correctamente.',
            'data' => UsuarioResource::make($usuario)->resolve(),
        ]);
    }

    public function contexto(): JsonResponse
    {
        $user = request()->user()?->load(['roles.permissions']);

        return response()->json([
            'success' => true,
            'message' => 'Contexto de seguridad obtenido correctamente.',
            'data' => [
                'user' => $user ? UsuarioResource::make($user)->resolve() : null,
                'roles' => $user?->roles ? RolResource::collection($user->roles)->resolve() : [],
                'permissions' => $user?->permission_slugs ?? [],
            ],
        ]);
    }
}
