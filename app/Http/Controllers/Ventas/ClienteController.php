<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shared\UpdateEstadoRequest;
use App\Http\Requests\Venta\StoreClienteRequest;
use App\Http\Requests\Venta\UpdateClienteRequest;
use App\Http\Resources\ClienteResource;
use App\Models\Cliente;
use App\Services\Ventas\ClienteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClienteController extends Controller
{
    public function __construct(
        private readonly ClienteService $service,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Clientes obtenidos correctamente.',
            'data' => ClienteResource::collection($this->service->listar([
                'search' => $request->string('search')->toString(),
                'estado' => $request->string('estado', 'todos')->toString(),
            ]))->resolve(),
            'meta' => $this->service->meta(),
        ]);
    }

    public function store(StoreClienteRequest $request): JsonResponse
    {
        $cliente = $this->service->crear($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Cliente registrado correctamente.',
            'data' => ClienteResource::make($cliente)->resolve(),
        ], 201);
    }

    public function show(Cliente $cliente): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Detalle del cliente obtenido correctamente.',
            'data' => ClienteResource::make($cliente)->resolve(),
        ]);
    }

    public function update(UpdateClienteRequest $request, Cliente $cliente): JsonResponse
    {
        $cliente = $this->service->actualizar($cliente, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Cliente actualizado correctamente.',
            'data' => ClienteResource::make($cliente)->resolve(),
        ]);
    }

    public function updateEstado(UpdateEstadoRequest $request, Cliente $cliente): JsonResponse
    {
        $cliente = $this->service->cambiarEstado($cliente, (bool) $request->validated('estado'));

        return response()->json([
            'success' => true,
            'message' => 'Estado del cliente actualizado correctamente.',
            'data' => ClienteResource::make($cliente)->resolve(),
        ]);
    }
}
