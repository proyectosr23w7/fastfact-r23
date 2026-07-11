<?php

namespace App\Http\Controllers\Facturacion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Facturacion\StoreCufdRequest;
use App\Http\Resources\CufdResource;
use App\Services\Facturacion\CufdService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CufdController extends Controller
{
    public function __construct(
        private readonly CufdService $service,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Historial de CUFD obtenido correctamente.',
            'data' => CufdResource::collection($this->service->listar([
                'sucursal_id' => $request->integer('sucursal_id') ?: null,
                'punto_venta_id' => $request->integer('punto_venta_id') ?: null,
                'ambiente_facturacion' => $request->string('ambiente_facturacion')->toString() ?: null,
                'estado' => $request->query('estado'),
            ], $request->user()))->resolve(),
            'meta' => $this->service->meta($request->user()),
        ]);
    }

    public function store(StoreCufdRequest $request): JsonResponse
    {
        $cufd = $this->service->registrar(
            $request->validated(),
            $request->user()->id,
            $request->user(),
        );

        return response()->json([
            'success' => true,
            'message' => 'CUFD registrado correctamente.',
            'data' => CufdResource::make($cufd)->resolve(),
        ], 201);
    }
}
