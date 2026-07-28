<?php

namespace App\Http\Controllers\Reportes;

use App\Http\Controllers\Controller;
use App\Services\Reportes\ReporteFacturacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ReporteFacturacionController extends Controller
{
    public function __construct(
        private readonly ReporteFacturacionService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Reporte de facturacion obtenido correctamente.',
            'data' => $this->service->generar($this->filters($request), $request->user()),
        ]);
    }

    public function excel(Request $request): Response
    {
        $export = $this->service->exportarExcel($this->filters($request), $request->user());

        return response($export['content'], 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$export['filename'].'"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    public function pdf(Request $request): Response
    {
        $export = $this->service->exportarPdf($this->filters($request), $request->user());

        return response($export['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$export['filename'].'"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    private function filters(Request $request): array
    {
        return [
            'fecha_desde' => $request->string('fecha_desde')->toString(),
            'fecha_hasta' => $request->string('fecha_hasta')->toString(),
            'sucursal_id' => $request->integer('sucursal_id') ?: null,
            'punto_venta_id' => $request->integer('punto_venta_id') ?: null,
            'user_id' => $request->integer('user_id') ?: null,
            'estado_factura' => $request->string('estado_factura')->toString(),
            'codigo_metodo_pago' => $request->string('codigo_metodo_pago')->toString(),
        ];
    }
}
