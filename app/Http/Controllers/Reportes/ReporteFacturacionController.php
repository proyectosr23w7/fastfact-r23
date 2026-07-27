<?php

namespace App\Http\Controllers\Reportes;

use App\Http\Controllers\Controller;
use App\Services\Reportes\ReporteFacturacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            'data' => $this->service->generar([
                'fecha_desde' => $request->string('fecha_desde')->toString(),
                'fecha_hasta' => $request->string('fecha_hasta')->toString(),
                'sucursal_id' => $request->integer('sucursal_id') ?: null,
                'punto_venta_id' => $request->integer('punto_venta_id') ?: null,
                'user_id' => $request->integer('user_id') ?: null,
                'estado_factura' => $request->string('estado_factura')->toString(),
                'codigo_metodo_pago' => $request->string('codigo_metodo_pago')->toString(),
            ], $request->user()),
        ]);
    }
}
