<?php

namespace App\Http\Controllers\Facturacion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Facturacion\AnularFacturaRequest;
use App\Http\Requests\Facturacion\ConsultarFacturaRequest;
use App\Http\Requests\Facturacion\EmitirFacturaDirectaRequest;
use App\Http\Resources\FacturaResource;
use App\Models\Factura;
use App\Services\Facturacion\FacturaCorreoService;
use App\Services\Facturacion\FacturaDirectaService;
use App\Services\Facturacion\FacturaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class FacturaController extends Controller
{
    public function __construct(
        private readonly FacturaService $service,
        private readonly FacturaDirectaService $facturaDirectaService,
        private readonly FacturaCorreoService $facturaCorreoService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Facturas obtenidas correctamente.',
            'data' => FacturaResource::collection($this->service->listar([
                'search' => $request->string('search')->toString(),
                'scope' => $request->string('scope')->toString(),
                'fecha_desde' => $request->string('fecha_desde')->toString(),
                'fecha_hasta' => $request->string('fecha_hasta')->toString(),
                'cliente_id' => $request->integer('cliente_id') ?: null,
                'sucursal_id' => $request->integer('sucursal_id') ?: null,
                'punto_venta_id' => $request->integer('punto_venta_id') ?: null,
                'estado_factura' => $request->string('estado_factura')->toString(),
            ], $request->user()))->resolve(),
            'meta' => $this->service->meta($request->user(), [
                'search' => $request->string('search')->toString(),
                'fecha_desde' => $request->string('fecha_desde')->toString(),
                'fecha_hasta' => $request->string('fecha_hasta')->toString(),
                'cliente_id' => $request->integer('cliente_id') ?: null,
                'sucursal_id' => $request->integer('sucursal_id') ?: null,
                'punto_venta_id' => $request->integer('punto_venta_id') ?: null,
            ]),
        ]);
    }

    public function show(Request $request, Factura $factura): JsonResponse
    {
        $this->service->autorizarAcceso($factura, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Detalle de factura obtenido correctamente.',
            'data' => FacturaResource::make(
                $factura->load([
                    'detalles.articulo.unidadMedida',
                    'cliente',
                    'sucursal',
                    'puntoVenta',
                    'user',
                    'cuis',
                    'cufd',
                    'cafc',
                    'eventoSignificativo.cafc',
                    'anulaciones.user',
                ]),
            )->resolve(),
        ]);
    }

    public function emitirDirecta(EmitirFacturaDirectaRequest $request): JsonResponse
    {
        $factura = $this->facturaDirectaService->emitir($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Factura directa emitida correctamente.',
            'data' => FacturaResource::make($factura)->resolve(),
        ], 201);
    }

    public function consultar(ConsultarFacturaRequest $request, Factura $factura): JsonResponse
    {
        $this->service->autorizarAcceso($factura, $request->user());
        $factura = $this->service->consultar($factura);

        return response()->json([
            'success' => true,
            'message' => 'Estado de factura consultado correctamente.',
            'data' => FacturaResource::make($factura)->resolve(),
        ]);
    }

    public function anular(AnularFacturaRequest $request, Factura $factura): JsonResponse
    {
        $this->service->autorizarAcceso($factura, $request->user());
        $factura = $this->service->anular($factura, $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Proceso de anulacion ejecutado correctamente.',
            'data' => FacturaResource::make($factura)->resolve(),
        ]);
    }

    public function revertirAnulacion(Request $request, Factura $factura): JsonResponse
    {
        $this->service->autorizarAcceso($factura, $request->user());
        $factura = $this->service->revertirAnulacion($factura, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Reversion de anulacion ejecutada correctamente.',
            'data' => FacturaResource::make($factura)->resolve(),
        ]);
    }

    public function reenviarCorreo(Request $request, Factura $factura): JsonResponse
    {
        $this->service->autorizarAcceso($factura, $request->user());

        $data = $request->validate([
            'correo' => ['required', 'email', 'max:150'],
            'actualizar_cliente' => ['sometimes', 'boolean'],
        ]);

        $tipo = $this->facturaCorreoService->reenviarSegunEstado(
            $factura,
            (string) $data['correo'],
            (bool) ($data['actualizar_cliente'] ?? false),
        );

        return response()->json([
            'success' => true,
            'message' => 'Correo reenviado correctamente.',
            'data' => [
                'tipo' => $tipo,
                'correo' => $data['correo'],
                'cliente' => $factura->cliente?->fresh(),
            ],
        ]);
    }

    public function downloadXml(Request $request, Factura $factura): Response
    {
        $this->service->autorizarAcceso($factura, $request->user());

        return $this->service->downloadXml($factura);
    }

    public function downloadPdf(Request $request, Factura $factura): Response
    {
        $this->service->autorizarAcceso($factura, $request->user());

        return $this->service->downloadPdf($factura);
    }
}
