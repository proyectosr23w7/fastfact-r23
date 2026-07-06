<?php

namespace App\Http\Middleware;

use App\Models\Configuracion\Empresa;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmpresaConfigurada
{
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        if (Empresa::query()->exists()) {
            return $next($request);
        }

        return redirect()->route('configuracion.empresa.page');
    }
}
