<?php

namespace App\Services\Ventas;

use App\Models\Cliente;
use App\Models\SinDocumentoIdentidad;
use App\Repositories\Ventas\ClienteRepository;
use Illuminate\Database\Eloquent\Collection;

class ClienteService
{
    public function __construct(
        private readonly ClienteRepository $repository,
    ) {
    }

    public function activos(): Collection
    {
        return $this->repository->allActive();
    }

    public function listar(array $filters = []): Collection
    {
        $estado = match ($filters['estado'] ?? 'todos') {
            'activos' => true,
            'inactivos' => false,
            default => null,
        };

        return $this->repository->all($estado, $filters['search'] ?? null);
    }

    public function crear(array $data): Cliente
    {
        $data['estado'] = $data['estado'] ?? true;
        $data['codigo'] = $this->siguienteCodigo();
        $data = $this->normalizarCliente($data);

        return $this->repository->create($data);
    }

    public function actualizar(Cliente $cliente, array $data): Cliente
    {
        $data['estado'] = $data['estado'] ?? $cliente->estado;
        $data = $this->normalizarCliente($data, $cliente);

        return $this->repository->update($cliente, $data);
    }

    public function cambiarEstado(Cliente $cliente, bool $estado): Cliente
    {
        return $this->repository->update($cliente, ['estado' => $estado]);
    }

    public function meta(): array
    {
        return [
            'documentos_identidad' => SinDocumentoIdentidad::query()
                ->where('estado', true)
                ->orderBy('codigo_clasificador')
                ->get(['codigo_clasificador', 'descripcion'])
                ->map(fn (SinDocumentoIdentidad $documento) => [
                    'codigo_clasificador' => $documento->codigo_clasificador,
                    'descripcion' => $documento->descripcion,
                ]),
        ];
    }

    private function normalizarCliente(array $data, ?Cliente $cliente = null): array
    {
        $data['nombre'] = trim((string) ($data['razon_social'] ?? $data['nit_ci'] ?? $cliente?->codigo ?? $data['codigo'] ?? ''));
        $data['complemento'] = ($data['tipo_documento_identidad'] ?? null) === '1'
            ? (($data['complemento'] ?? null) ?: null)
            : null;

        return $data;
    }

    private function siguienteCodigo(): string
    {
        $nextId = (int) Cliente::query()->max('id') + 1;

        do {
            $codigo = 'CLI-'.str_pad((string) $nextId, 4, '0', STR_PAD_LEFT);
            $nextId++;
        } while (Cliente::query()->where('codigo', $codigo)->exists());

        return $codigo;
    }
}
