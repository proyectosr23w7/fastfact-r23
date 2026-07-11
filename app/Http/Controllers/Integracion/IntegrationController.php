<?php

namespace App\Http\Controllers\Integracion;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArticuloResource;
use App\Http\Resources\ClienteResource;
use App\Http\Resources\CufdResource;
use App\Http\Resources\CuisResource;
use App\Http\Resources\FacturaResource;
use App\Models\Articulo;
use App\Models\Cliente;
use App\Models\Configuracion\Configuracion;
use App\Models\Factura;
use App\Services\Facturacion\CufdService;
use App\Services\Facturacion\CuisService;
use App\Services\Facturacion\FacturaDirectaService;
use App\Services\Facturacion\FacturaService;
use App\Services\Inventario\ArticuloService;
use App\Services\Ventas\ClienteService;
use App\Support\OperationalContextScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class IntegrationController extends Controller
{
    public function __construct(
        private readonly ArticuloService $articuloService,
        private readonly ClienteService $clienteService,
        private readonly FacturaDirectaService $facturaDirectaService,
        private readonly FacturaService $facturaService,
        private readonly CuisService $cuisService,
        private readonly CufdService $cufdService,
    ) {}

    public function productos(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Productos obtenidos correctamente.',
            'data' => ArticuloResource::collection($this->articuloService->listar([
                'search' => $request->string('search')->toString(),
                'categoria_id' => $request->integer('categoria_id') ?: null,
                'marca_id' => $request->integer('marca_id') ?: null,
            ]))->resolve(),
        ]);
    }

    public function registrarProducto(Request $request): JsonResponse
    {
        $articulo = $this->articuloService->crear($this->validateProducto($request));

        return response()->json([
            'success' => true,
            'message' => 'Producto registrado correctamente.',
            'data' => ArticuloResource::make($articulo)->resolve(),
        ], 201);
    }

    public function actualizarProducto(Request $request, Articulo $articulo): JsonResponse
    {
        $articulo = $this->articuloService->actualizar($articulo, $this->validateProducto($request, $articulo));

        return response()->json([
            'success' => true,
            'message' => 'Producto actualizado correctamente.',
            'data' => ArticuloResource::make($articulo)->resolve(),
        ]);
    }

    public function clientes(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Clientes obtenidos correctamente.',
            'data' => ClienteResource::collection($this->clienteService->listar([
                'search' => $request->string('search')->toString(),
                'estado' => $request->string('estado', 'todos')->toString(),
            ]))->resolve(),
        ]);
    }

    public function registrarCliente(Request $request): JsonResponse
    {
        $cliente = $this->clienteService->crear($this->validateCliente($request));

        return response()->json([
            'success' => true,
            'message' => 'Cliente registrado correctamente.',
            'data' => ClienteResource::make($cliente)->resolve(),
        ], 201);
    }

    public function actualizarCliente(Request $request, Cliente $cliente): JsonResponse
    {
        $cliente = $this->clienteService->actualizar($cliente, $this->validateCliente($request, $cliente));

        return response()->json([
            'success' => true,
            'message' => 'Cliente actualizado correctamente.',
            'data' => ClienteResource::make($cliente)->resolve(),
        ]);
    }

    public function cuisActual(Request $request): JsonResponse
    {
        [$sucursalId, $puntoVentaId] = $this->contextoOperativo($request);
        $configuracion = Configuracion::current();
        $ambiente = $configuracion?->ambiente_facturacion ?: 'piloto';

        $cuis = \App\Models\Cuis::query()
            ->where('sucursal_id', $sucursalId)
            ->where('punto_venta_id', $puntoVentaId)
            ->where('ambiente_facturacion', $ambiente)
            ->where('estado', true)
            ->where(function ($query) {
                $query->whereNull('fecha_vigencia')
                    ->orWhere('fecha_vigencia', '>=', now());
            })
            ->latest('fecha_vigencia')
            ->latest('id')
            ->first();

        return response()->json([
            'success' => true,
            'message' => $cuis ? 'CUIS vigente obtenido correctamente.' : 'No existe CUIS vigente para el contexto operativo.',
            'data' => $cuis ? CuisResource::make($cuis->load(['sucursal', 'puntoVenta', 'user']))->resolve() : null,
        ]);
    }

    public function asegurarCuis(Request $request): JsonResponse
    {
        [$sucursalId, $puntoVentaId] = $this->contextoOperativo($request);

        $cuis = $this->cuisService->registrar([
            'sucursal_id' => $sucursalId,
            'punto_venta_id' => $puntoVentaId,
        ], (int) $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'CUIS vigente asegurado correctamente.',
            'data' => CuisResource::make($cuis)->resolve(),
        ]);
    }

    public function cufdActual(Request $request): JsonResponse
    {
        [$sucursalId, $puntoVentaId] = $this->contextoOperativo($request);
        $configuracion = Configuracion::current();
        $ambiente = $configuracion?->ambiente_facturacion ?: 'piloto';

        $cufd = \App\Models\Cufd::query()
            ->where('sucursal_id', $sucursalId)
            ->where('punto_venta_id', $puntoVentaId)
            ->where('ambiente_facturacion', $ambiente)
            ->where('estado', true)
            ->where(function ($query) {
                $query->whereNull('fecha_vigencia')
                    ->orWhere('fecha_vigencia', '>=', now());
            })
            ->latest('fecha_vigencia')
            ->latest('id')
            ->first();

        return response()->json([
            'success' => true,
            'message' => $cufd ? 'CUFD vigente obtenido correctamente.' : 'No existe CUFD vigente para el contexto operativo.',
            'data' => $cufd ? CufdResource::make($cufd->load(['sucursal', 'puntoVenta', 'user']))->resolve() : null,
        ]);
    }

    public function asegurarCufd(Request $request): JsonResponse
    {
        [$sucursalId, $puntoVentaId] = $this->contextoOperativo($request);

        $this->cuisService->generarSiNoExiste($sucursalId, $puntoVentaId, (int) $request->user()->id);
        $this->cufdService->generarSiNoExiste($sucursalId, $puntoVentaId, (int) $request->user()->id);

        $cufd = $this->cufdService->registrar([
            'sucursal_id' => $sucursalId,
            'punto_venta_id' => $puntoVentaId,
        ], (int) $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'CUFD vigente asegurado correctamente.',
            'data' => CufdResource::make($cufd)->resolve(),
        ]);
    }

    public function emitirFactura(Request $request): JsonResponse
    {
        [$sucursalId, $puntoVentaId] = $this->contextoOperativo($request);

        $this->cuisService->generarSiNoExiste($sucursalId, $puntoVentaId, (int) $request->user()->id);
        $this->cufdService->generarSiNoExiste($sucursalId, $puntoVentaId, (int) $request->user()->id);

        $data = Validator::make(
            array_merge($request->all(), [
                'sucursal_id' => $sucursalId,
                'punto_venta_id' => $puntoVentaId,
                'origen' => $request->input('origen', 'integracion'),
            ]),
            $this->facturaRules(),
        )->validate();

        $factura = $this->facturaDirectaService->emitir($data, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Factura emitida correctamente desde integracion.',
            'data' => FacturaResource::make($factura)->resolve(),
        ], 201);
    }

    public function anularFactura(Request $request, Factura $factura): JsonResponse
    {
        $this->facturaService->autorizarAcceso($factura, $request->user());

        $data = Validator::make($request->all(), [
            'codigo_motivo_anulacion' => ['required', 'string', Rule::exists('sin_motivos_anulacion', 'codigo_clasificador')->where('estado', true)],
            'descripcion_motivo' => ['nullable', 'string', 'max:255'],
        ])->validate();

        $factura = $this->facturaService->anular($factura, $data, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Factura anulada correctamente desde integracion.',
            'data' => FacturaResource::make($factura)->resolve(),
        ]);
    }

    public function revertirFactura(Request $request, Factura $factura): JsonResponse
    {
        $this->facturaService->autorizarAcceso($factura, $request->user());

        $factura = $this->facturaService->revertirAnulacion($factura, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Reversion de factura ejecutada correctamente desde integracion.',
            'data' => FacturaResource::make($factura)->resolve(),
        ]);
    }

    private function contextoOperativo(Request $request): array
    {
        $sucursalId = (int) $request->input('sucursal_id');
        $puntoVentaId = (int) $request->input('punto_venta_id');

        abort_if($sucursalId <= 0 || $puntoVentaId <= 0, 422, 'La solicitud de integracion debe enviar sucursal_id y punto_venta_id.');

        $exists = \App\Models\Configuracion\PuntoVenta::query()
            ->whereKey($puntoVentaId)
            ->where('sucursal_id', $sucursalId)
            ->exists();

        abort_unless($exists, 422, 'El punto de venta no pertenece a la sucursal indicada.');
        OperationalContextScope::authorize($request->user(), $sucursalId, $puntoVentaId);

        return [$sucursalId, $puntoVentaId];
    }

    private function validateProducto(Request $request, ?Articulo $articulo = null): array
    {
        $rules = [
            'codigo_generico' => ['required', 'string', 'max:100', Rule::unique('articulos', 'codigo_generico')->ignore($articulo?->id)],
            'codigo_barras' => ['nullable', 'string', 'max:100', Rule::unique('articulos', 'codigo_barras')->ignore($articulo?->id)],
            'nombre' => ['required', 'string', 'max:200'],
            'descripcion' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['nullable', 'string', 'max:80'],
            'alias' => ['nullable', 'array'],
            'alias.*' => ['nullable', 'string', 'max:120'],
            'atributos' => ['nullable', 'array'],
            'atributos.*.atributo' => ['nullable', 'string', 'max:120'],
            'atributos.*.valor' => ['nullable', 'string', 'max:200'],
            'categoria_id' => ['nullable', 'integer', Rule::exists('categorias', 'id')],
            'marca_id' => ['nullable', 'integer', Rule::exists('marcas', 'id')],
            'codigo_actividad_economica' => ['nullable', 'string', Rule::exists('sin_actividades', 'codigo_clasificador')->where('estado', true)],
            'codigo_producto_sin' => ['nullable', 'string', Rule::exists('sin_productos_servicios', 'codigo_producto')->where('estado', true)],
            'codigo_unidad_medida_siat' => ['required', 'string', Rule::exists('sin_unidades_medida', 'codigo_clasificador')->where(fn ($query) => $query->where('estado', true)->where('habilitado_uso', true))],
            'stock_minimo' => ['nullable', 'numeric', 'min:0'],
            'precio_base' => ['required', 'numeric', 'min:0'],
            'estado' => ['sometimes', 'boolean'],
            'precios' => ['nullable', 'array'],
            'precios.*.cantidad_minima' => ['required_with:precios.*.precio', 'numeric', 'min:1'],
            'precios.*.precio' => ['required_with:precios.*.cantidad_minima', 'numeric', 'min:0'],
            'precios.*.tipo_precio' => ['nullable', 'string', 'max:50'],
            'precios.*.estado' => ['sometimes', 'boolean'],
        ];

        return Validator::make($request->all(), $rules)->after(function ($validator) use ($request) {
            $codigoActividad = trim((string) $request->input('codigo_actividad_economica', ''));
            $codigoProducto = trim((string) $request->input('codigo_producto_sin', ''));

            if (($codigoActividad === '') !== ($codigoProducto === '')) {
                $validator->errors()->add('codigo_producto_sin', 'La actividad economica y el producto SIN deben enviarse juntos.');
            }
        })->validate();
    }

    private function validateCliente(Request $request, ?Cliente $cliente = null): array
    {
        return Validator::make($request->all(), [
            'razon_social' => ['required', 'string', 'max:200'],
            'nit_ci' => [
                'required',
                'string',
                'max:50',
                Rule::unique('clientes', 'nit_ci')
                    ->ignore($cliente?->id)
                    ->where(fn ($query) => $query
                        ->where('tipo_documento_identidad', $request->input('tipo_documento_identidad'))
                        ->where('complemento', $request->input('complemento'))),
            ],
            'tipo_documento_identidad' => ['required', 'string', 'max:10'],
            'complemento' => ['nullable', 'string', 'max:20'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'correo' => ['nullable', 'email', 'max:150'],
            'estado' => ['sometimes', 'boolean'],
        ])->after(function ($validator) use ($request) {
            $tipo = (string) $request->input('tipo_documento_identidad', '');
            $documento = (string) $request->input('nit_ci', '');

            if (in_array($tipo, ['1', '5'], true) && ! preg_match('/^[0-9]+$/', $documento)) {
                $validator->errors()->add('nit_ci', 'Para CI y NIT el numero de documento debe ser numerico.');
            }

            if (preg_match('/^0+$/', trim($documento)) === 1) {
                $validator->errors()->add('nit_ci', 'El numero de documento debe ser distinto de 0 para emitir factura.');
            }
        })->validate();
    }

    private function facturaRules(): array
    {
        return [
            'cliente_id' => ['required', 'integer', Rule::exists('clientes', 'id')],
            'sucursal_id' => ['required', 'integer', Rule::exists('sucursales', 'id')],
            'punto_venta_id' => ['required', 'integer', Rule::exists('puntos_venta', 'id')],
            'codigo_metodo_pago' => ['nullable', 'string', Rule::exists('sin_metodos_pago', 'codigo_clasificador')->where(fn ($query) => $query->where('estado', true)->where('habilitado_venta', true))],
            'numero_tarjeta' => ['nullable', 'string', 'max:32'],
            'monto_gift_card' => ['nullable', 'numeric', 'gt:0'],
            'codigo_documento_identidad' => ['nullable', 'string', Rule::exists('sin_documentos_identidad', 'codigo_clasificador')->where('estado', true)],
            'referencia_externa' => ['nullable', 'string', 'max:120'],
            'origen' => ['nullable', 'string', 'max:40'],
            'observacion' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.articulo_id' => ['nullable', 'integer', Rule::exists('articulos', 'id')],
            'detalles.*.actividad_economica' => ['required', 'string', 'max:20'],
            'detalles.*.codigo_producto_sin' => ['required', 'integer'],
            'detalles.*.codigo_producto' => ['required', 'string', 'max:80'],
            'detalles.*.descripcion' => ['required', 'string', 'max:500'],
            'detalles.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'detalles.*.unidad_medida' => ['required', 'integer'],
            'detalles.*.precio_unitario' => ['required', 'numeric', 'gte:0'],
            'detalles.*.monto_descuento' => ['nullable', 'numeric', 'gte:0'],
            'detalles.*.numero_serie' => ['nullable', 'string', 'max:100'],
            'detalles.*.numero_imei' => ['nullable', 'string', 'max:100'],
            'detalles.*.metadata' => ['nullable', 'array'],
        ];
    }
}
