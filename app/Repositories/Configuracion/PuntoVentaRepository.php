<?php

namespace App\Repositories\Configuracion;

use App\Models\Configuracion\PuntoVenta;
use Illuminate\Database\Eloquent\Collection;

class PuntoVentaRepository
{
    public function allForIndex(): Collection
    {
        return PuntoVenta::query()
            ->with(['sucursal:id,codigo,nombre', 'cuisVigente'])
            ->orderBy('nombre')
            ->get();
    }

    public function create(array $data): PuntoVenta
    {
        return PuntoVenta::query()->create($data);
    }

    public function createDefaultForSucursal(int $sucursalId, string $sucursalNombre): PuntoVenta
    {
        return $this->create([
            'sucursal_id' => $sucursalId,
            'codigo' => 0,
            'nombre' => 'PUNTO DE VENTA 0 - '.$sucursalNombre,
            'descripcion' => 'Punto de venta creado automaticamente.',
            'tipo_impresion' => 'ticket',
            'estado' => true,
        ]);
    }

    public function update(PuntoVenta $puntoVenta, array $data): PuntoVenta
    {
        $puntoVenta->update($data);

        return $puntoVenta->refresh()->load(['sucursal:id,codigo,nombre', 'cuisVigente']);
    }

    public function delete(PuntoVenta $puntoVenta): void
    {
        $puntoVenta->delete();
    }
}
