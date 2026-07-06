<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Configuracion\StoreRoleRequest;
use App\Http\Requests\Configuracion\UpdateRoleRequest;
use App\Http\Resources\Configuracion\RoleResource;
use App\Models\Configuracion\Role;
use App\Services\Configuracion\RoleService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function __construct(
        private readonly RoleService $service,
    ) {
    }

    public function index(): Response
    {
        return Inertia::render('configuracion/roles/IndexView', [
            'roles' => RoleResource::collection($this->service->listar())->resolve(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('configuracion/roles/CreateView');
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $this->service->crear($request->validated());

        return redirect()
            ->route('configuracion.roles.index')
            ->with('success', 'Rol creado correctamente.');
    }

    public function edit(Role $role): Response
    {
        return Inertia::render('configuracion/roles/EditView', [
            'role' => RoleResource::make($role)->resolve(),
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $this->service->actualizar($role, $request->validated());

        return redirect()
            ->route('configuracion.roles.index')
            ->with('success', 'Rol actualizado correctamente.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->service->eliminar($role);

        return redirect()
            ->route('configuracion.roles.index')
            ->with('success', 'Rol eliminado correctamente.');
    }
}
