<?php

namespace App\Http\Controllers\Facturacion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Facturacion\StoreCafcRequest;
use App\Http\Requests\Facturacion\UpdateCafcEstadoRequest;
use App\Http\Resources\CafcResource;
use App\Models\Cafc;
use App\Services\Facturacion\CafcService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CafcController extends Controller
{
    public function __construct(
        private readonly CafcService $service,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'CAFC obtenidos correctamente.',
            'data' => CafcResource::collection($this->service->listar([
                'sucursal_id' => $request->integer('sucursal_id') ?: null,
                'punto_venta_id' => $request->integer('punto_venta_id') ?: null,
                'ambiente_facturacion' => $request->string('ambiente_facturacion')->toString() ?: null,
                'estado' => $request->query('estado'),
            ], $request->user()))->resolve(),
            'meta' => $this->service->meta($request->user()),
        ]);
    }

    public function store(StoreCafcRequest $request): JsonResponse
    {
        $cafc = $this->service->registrar($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'CAFC registrado correctamente.',
            'data' => CafcResource::make($cafc)->resolve(),
        ], 201);
    }

    public function update(StoreCafcRequest $request, Cafc $cafc): JsonResponse
    {
        $cafc = $this->service->actualizar($cafc, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'CAFC actualizado correctamente.',
            'data' => CafcResource::make($cafc)->resolve(),
        ]);
    }

    public function updateEstado(UpdateCafcEstadoRequest $request, Cafc $cafc): JsonResponse
    {
        $cafc = $this->service->actualizarEstado($cafc, (bool) $request->boolean('estado'), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Estado del CAFC actualizado correctamente.',
            'data' => CafcResource::make($cafc)->resolve(),
        ]);
    }
}
