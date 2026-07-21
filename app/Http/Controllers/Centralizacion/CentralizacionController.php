<?php

namespace App\Http\Controllers\Centralizacion;

use App\Http\Controllers\Controller;
use App\Services\Centralizacion\CentralizacionService;
use Illuminate\Http\JsonResponse;

class CentralizacionController extends Controller
{
    public function __construct(
        private readonly CentralizacionService $service,
    ) {}

    public function estado(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Estado centralizado obtenido correctamente.',
            'data' => $this->service->estado(),
        ]);
    }

    public function respaldoManifest(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Manifiesto de respaldo obtenido correctamente.',
            'data' => $this->service->respaldoManifest(),
        ]);
    }
}
