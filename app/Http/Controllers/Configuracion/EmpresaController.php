<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Configuracion\Empresa\StoreEmpresaRequest;
use App\Http\Requests\Configuracion\Empresa\UpdateEmpresaRequest;
use App\Http\Resources\Configuracion\EmpresaResource;
use App\Models\Configuracion\Empresa;
use App\Services\Configuracion\EmpresaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmpresaController extends Controller
{
    public function __construct(
        private readonly EmpresaService $service,
    ) {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => EmpresaResource::collection($this->service->listar())->resolve(),
        ]);
    }

    public function store(StoreEmpresaRequest $request): JsonResponse
    {
        $empresa = $this->service->crear($request->validated());

        return response()->json([
            'data' => EmpresaResource::make($empresa)->resolve(),
            'message' => 'Empresa registrada correctamente.',
        ], 201);
    }

    public function show(Empresa $empresa): JsonResponse
    {
        return response()->json([
            'data' => EmpresaResource::make($empresa)->resolve(),
        ]);
    }

    public function logo(Empresa $empresa): StreamedResponse
    {
        $path = $empresa->storedLogoPath();

        abort_unless($path !== null && Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path, null, [
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function update(UpdateEmpresaRequest $request, Empresa $empresa): JsonResponse
    {
        $empresa = $this->service->actualizar($empresa, $request->validated());

        return response()->json([
            'data' => EmpresaResource::make($empresa)->resolve(),
            'message' => 'Empresa actualizada correctamente.',
        ]);
    }

    public function destroy(Empresa $empresa): JsonResponse
    {
        $this->service->eliminar($empresa);

        return response()->json([
            'message' => 'Empresa eliminada correctamente.',
        ]);
    }
}
