<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Venta\AnularVentaRequest;
use App\Http\Requests\Venta\ConfirmVentaRequest;
use App\Http\Requests\Venta\StoreVentaRequest;
use App\Http\Requests\Venta\UpdateVentaRequest;
use App\Http\Resources\VentaCabeceraResource;
use App\Models\VentaCabecera;
use App\Services\Ventas\VentaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Http\Request;

class VentaController extends Controller
{
    public function __construct(
        private readonly VentaService $service,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $ventas = $this->service->listar([
            'search' => $request->string('search')->toString(),
            'fecha_desde' => $request->string('fecha_desde')->toString(),
            'fecha_hasta' => $request->string('fecha_hasta')->toString(),
            'cliente_id' => $request->integer('cliente_id') ?: null,
            'sucursal_id' => $request->integer('sucursal_id') ?: null,
            'punto_venta_id' => $request->integer('punto_venta_id') ?: null,
            'estado' => $request->string('estado')->toString(),
            'tipo_documento_venta' => $request->string('tipo_documento_venta')->toString(),
            'per_page' => $request->integer('per_page') ?: 10,
            'page' => $request->integer('page') ?: 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Ventas obtenidas correctamente.',
            'data' => VentaCabeceraResource::collection(
                $ventas->getCollection(),
            )->resolve(),
            'meta' => [
                ...$this->service->meta($request->user()),
                'summary' => $this->service->resumen(),
                'pagination' => [
                    'current_page' => $ventas->currentPage(),
                    'last_page' => $ventas->lastPage(),
                    'per_page' => $ventas->perPage(),
                    'total' => $ventas->total(),
                    'from' => $ventas->firstItem(),
                    'to' => $ventas->lastItem(),
                ],
            ],
        ]);
    }

    public function store(StoreVentaRequest $request): JsonResponse
    {
        $venta = $this->service->crear($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Venta registrada correctamente.',
            'data' => VentaCabeceraResource::make($venta)->resolve(),
        ], 201);
    }

    public function show(VentaCabecera $venta): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Detalle de venta obtenido correctamente.',
            'data' => VentaCabeceraResource::make(
                $venta->load([
                    'cliente',
                    'sucursal',
                    'puntoVenta',
                    'user',
                    'detalle.articulo',
                    'detalle.lotes',
                    'factura',
                ]),
            )->resolve(),
        ]);
    }

    public function update(UpdateVentaRequest $request, VentaCabecera $venta): JsonResponse
    {
        $venta = $this->service->actualizar($venta, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Venta actualizada correctamente.',
            'data' => VentaCabeceraResource::make($venta)->resolve(),
        ]);
    }

    public function confirmar(ConfirmVentaRequest $request, VentaCabecera $venta): JsonResponse
    {
        $venta = $this->service->confirmar($venta, $request->validated('observacion'));

        return response()->json([
            'success' => true,
            'message' => 'Venta confirmada correctamente.',
            'data' => VentaCabeceraResource::make($venta)->resolve(),
        ]);
    }

    public function anular(AnularVentaRequest $request, VentaCabecera $venta): JsonResponse
    {
        $venta = $this->service->anular($venta, $request->validated('observacion'));

        return response()->json([
            'success' => true,
            'message' => 'Venta anulada correctamente.',
            'data' => VentaCabeceraResource::make($venta)->resolve(),
        ]);
    }

    public function downloadPdf(VentaCabecera $venta): Response
    {
        return $this->service->downloadPdf($venta);
    }
}
