<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Configuracion\StorePersonalRequest;
use App\Http\Requests\Configuracion\UpdatePersonalRequest;
use App\Http\Resources\Configuracion\PersonalResource;
use App\Http\Resources\Configuracion\PuestoResource;
use App\Models\Configuracion\Personal;
use App\Models\Configuracion\Puesto;
use App\Services\Configuracion\PersonalService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PersonalController extends Controller
{
    public function __construct(
        private readonly PersonalService $service,
    ) {
    }

    public function index(): Response
    {
        return Inertia::render('configuracion/personal/IndexView', [
            'personal' => PersonalResource::collection($this->service->listar())->resolve(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('configuracion/personal/CreateView', [
            'puestos' => PuestoResource::collection(Puesto::query()->orderBy('nombre')->get())->resolve(),
        ]);
    }

    public function store(StorePersonalRequest $request): RedirectResponse
    {
        $this->service->crear($request->validated());

        return redirect()
            ->route('configuracion.personal.index')
            ->with('success', 'Personal registrado correctamente.');
    }

    public function edit(Personal $personal): Response
    {
        $personal->load('puesto:id,nombre');

        return Inertia::render('configuracion/personal/EditView', [
            'personalItem' => PersonalResource::make($personal)->resolve(),
            'puestos' => PuestoResource::collection(Puesto::query()->orderBy('nombre')->get())->resolve(),
        ]);
    }

    public function update(UpdatePersonalRequest $request, Personal $personal): RedirectResponse
    {
        $this->service->actualizar($personal, $request->validated());

        return redirect()
            ->route('configuracion.personal.index')
            ->with('success', 'Personal actualizado correctamente.');
    }

    public function destroy(Personal $personal): RedirectResponse
    {
        $this->service->eliminar($personal);

        return redirect()
            ->route('configuracion.personal.index')
            ->with('success', 'Registro de personal eliminado correctamente.');
    }
}
