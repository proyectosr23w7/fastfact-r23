<?php

namespace App\Repositories\Ventas;

use App\Models\Cliente;
use Illuminate\Database\Eloquent\Collection;

class ClienteRepository
{
    public function all(?bool $estado = null, ?string $search = null): Collection
    {
        return Cliente::query()
            ->when($estado !== null, fn ($query) => $query->where('estado', $estado))
            ->when(filled($search), fn ($query) => $query->where(function ($subQuery) use ($search) {
                $subQuery
                    ->where('razon_social', 'like', "%{$search}%")
                    ->orWhere('nombre', 'like', "%{$search}%")
                    ->orWhere('nit_ci', 'like', "%{$search}%")
                    ->orWhere('codigo', 'like', "%{$search}%");
            }))
            ->orderByDesc('estado')
            ->orderBy('razon_social')
            ->get();
    }

    public function allActive(): Collection
    {
        return $this->all(true);
    }

    public function create(array $data): Cliente
    {
        return Cliente::query()->create($data);
    }

    public function update(Cliente $cliente, array $data): Cliente
    {
        $cliente->update($data);

        return $cliente->refresh();
    }
}
