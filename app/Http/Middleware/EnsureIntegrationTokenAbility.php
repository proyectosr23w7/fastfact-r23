<?php

namespace App\Http\Middleware;

use App\Models\IntegrationApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIntegrationTokenAbility
{
    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        $token = $request->attributes->get('integration_token');

        abort_unless($token instanceof IntegrationApiToken, 401, 'Token de integracion requerido.');

        if ($abilities === []) {
            return $next($request);
        }

        abort_unless(
            collect($abilities)->contains(fn (string $ability) => $token->canUse($ability)),
            403,
            'El token no tiene permiso para esta operacion de centralizacion.',
        );

        return $next($request);
    }
}
