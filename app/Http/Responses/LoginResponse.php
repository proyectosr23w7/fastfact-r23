<?php

namespace App\Http\Responses;

use App\Models\Configuracion\Configuracion;
use App\Models\Configuracion\PuntoVenta;
use App\Services\Facturacion\CuisService;
use App\Services\Facturacion\CufdService;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function __construct(
        private readonly CuisService $cuisService,
        private readonly CufdService $cufdService,
    ) {
    }

    public function toResponse($request)
    {
        $user = $request->user();
        $configuracion = Configuracion::current();

        if (! $configuracion?->facturacionSiatActiva()) {
            return $request->wantsJson()
                ? response()->json(['two_factor' => false])
                : redirect()->intended(config('fortify.home'));
        }

        if ($user) {
            PuntoVenta::query()
                ->with('sucursal:id,codigo,nombre,estado')
                ->where('estado', true)
                ->get(['id', 'sucursal_id', 'codigo', 'nombre'])
                ->filter(fn ($puntoVenta) => (bool) optional($puntoVenta->sucursal)->estado)
                ->each(fn ($puntoVenta) => $this->generarCodigosContexto(
                    (int) $puntoVenta->sucursal_id,
                    (int) $puntoVenta->id,
                    (int) $user->id,
                ));
        }

        return $request->wantsJson()
            ? response()->json(['two_factor' => false])
            : redirect()->intended(config('fortify.home'));
    }

    private function generarCodigosContexto(int $sucursalId, int $puntoVentaId, int $userId): void
    {
        // Verificacion no bloqueante: el login no debe fallar si SIAT esta caido o sin credenciales.
        $this->cuisService->generarSiNoExiste(
            $sucursalId,
            $puntoVentaId,
            $userId,
            silencioso: true,
        );

        $this->cufdService->generarSiNoExiste(
            $sucursalId,
            $puntoVentaId,
            $userId,
            silencioso: true,
        );
    }
}
