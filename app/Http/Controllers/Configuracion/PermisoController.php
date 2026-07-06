<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Configuracion\StorePermisoRequest;
use App\Http\Requests\Configuracion\UpdatePermisoRequest;
use App\Http\Resources\Configuracion\PermisoResource;
use App\Models\Configuracion\Permiso;
use App\Services\Configuracion\PermisoService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PermisoController extends Controller
{
    public function __construct(
        private readonly PermisoService $service,
    ) {
    }

    public function index(): Response
    {
        return Inertia::render('configuracion/permisos/IndexView', [
            'permisos' => PermisoResource::collection($this->service->listar())->resolve(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('configuracion/permisos/CreateView');
    }

    public function store(StorePermisoRequest $request): RedirectResponse
    {
        $this->service->crear($request->validated());

        return redirect()
            ->route('configuracion.permisos.index')
            ->with('success', 'Permiso creado correctamente.');
    }

    public function edit(Permiso $permiso): Response
    {
        return Inertia::render('configuracion/permisos/EditView', [
            'permiso' => PermisoResource::make($permiso)->resolve(),
        ]);
    }

    public function update(UpdatePermisoRequest $request, Permiso $permiso): RedirectResponse
    {
        $this->service->actualizar($permiso, $request->validated());

        return redirect()
            ->route('configuracion.permisos.index')
            ->with('success', 'Permiso actualizado correctamente.');
    }

    public function destroy(Permiso $permiso): RedirectResponse
    {
        $this->service->eliminar($permiso);

        return redirect()
            ->route('configuracion.permisos.index')
            ->with('success', 'Permiso eliminado correctamente.');
    }
}
