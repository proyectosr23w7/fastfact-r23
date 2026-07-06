<?php

namespace App\Http\Controllers\Facturacion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Facturacion\StoreCuisRequest;
use App\Http\Resources\CuisResource;
use App\Services\Facturacion\CuisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CuisController extends Controller
{
    public function __construct(
        private readonly CuisService $service,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Historial de CUIS obtenido correctamente.',
            'data' => CuisResource::collection($this->service->listar([
                'sucursal_id' => $request->integer('sucursal_id') ?: null,
                'punto_venta_id' => $request->integer('punto_venta_id') ?: null,
                'ambiente_facturacion' => $request->string('ambiente_facturacion')->toString() ?: null,
            ]))->resolve(),
            'meta' => $this->service->meta(),
        ]);
    }

    public function store(StoreCuisRequest $request): JsonResponse
    {
        $cuis = $this->service->registrar($request->validated(), $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'CUIS registrado correctamente.',
            'data' => CuisResource::make($cuis)->resolve(),
        ], 201);
    }
}
