<?php

namespace App\Http\Controllers\Centralizacion;

use App\Http\Controllers\Controller;
use App\Services\Centralizacion\CentralizacionService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

    public function backups(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Backups obtenidos correctamente.',
            'data' => $this->service->listarBackups(),
        ]);
    }

    public function generarBackup(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Backup generado correctamente.',
            'data' => $this->service->generarBackup(),
        ], 201);
    }

    public function descargarBackup(string $filename): BinaryFileResponse
    {
        return response()->download($this->service->backupPath($filename), $filename);
    }
}
