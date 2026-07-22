<?php

namespace App\Http\Controllers\Importacion;

use App\Http\Controllers\Controller;
use App\Services\Importacion\ImportacionMasivaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImportacionMasivaController extends Controller
{
    public function __construct(
        private readonly ImportacionMasivaService $service,
    ) {}

    public function clientes(Request $request): JsonResponse
    {
        $data = $request->validate([
            'rows' => ['required', 'array', 'min:1', 'max:2000'],
            'rows.*' => ['required', 'array'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Importacion de clientes procesada.',
            'data' => $this->service->clientes($data['rows']),
        ]);
    }

    public function articulos(Request $request): JsonResponse
    {
        $data = $request->validate([
            'rows' => ['required', 'array', 'min:1', 'max:2000'],
            'rows.*' => ['required', 'array'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Importacion de productos procesada.',
            'data' => $this->service->articulos($data['rows']),
        ]);
    }
}
