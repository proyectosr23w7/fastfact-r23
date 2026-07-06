<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Configuracion\StorePuestoRequest;
use App\Http\Requests\Configuracion\UpdatePuestoRequest;
use App\Http\Resources\Configuracion\PuestoResource;
use App\Models\Configuracion\Puesto;
use App\Services\Configuracion\PuestoService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PuestoController extends Controller
{
    public function __construct(
        private readonly PuestoService $service,
    ) {
    }

    public function index(): Response
    {
        return Inertia::render('configuracion/puestos/IndexView', [
            'puestos' => PuestoResource::collection($this->service->listar())->resolve(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('configuracion/puestos/CreateView');
    }

    public function store(StorePuestoRequest $request): RedirectResponse
    {
        $this->service->crear($request->validated());

        return redirect()
            ->route('configuracion.puestos.index')
            ->with('success', 'Puesto creado correctamente.');
    }

    public function edit(Puesto $puesto): Response
    {
        return Inertia::render('configuracion/puestos/EditView', [
            'puesto' => PuestoResource::make($puesto)->resolve(),
        ]);
    }

    public function update(UpdatePuestoRequest $request, Puesto $puesto): RedirectResponse
    {
        $this->service->actualizar($puesto, $request->validated());

        return redirect()
            ->route('configuracion.puestos.index')
            ->with('success', 'Puesto actualizado correctamente.');
    }

    public function destroy(Puesto $puesto): RedirectResponse
    {
        $this->service->eliminar($puesto);

        return redirect()
            ->route('configuracion.puestos.index')
            ->with('success', 'Puesto eliminado correctamente.');
    }
}
