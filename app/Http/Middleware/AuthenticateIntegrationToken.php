<?php

namespace App\Http\Middleware;

use App\Models\IntegrationApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateIntegrationToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $plainTextToken = $request->bearerToken();

        abort_if(blank($plainTextToken), 401, 'Token de integracion requerido.');

        $token = IntegrationApiToken::query()
            ->with(['user.roles.permissions'])
            ->where('token_hash', IntegrationApiToken::hashPlainTextToken((string) $plainTextToken))
            ->first();

        abort_unless($token && $token->isUsable(), 401, 'Token de integracion invalido o vencido.');
        abort_unless($token->user && (bool) $token->user->estado, 403, 'El usuario asociado al token no esta activo.');

        $request->attributes->set('integration_token', $token);
        $request->setUserResolver(fn () => $token->user);

        $token->forceFill(['last_used_at' => now()])->save();

        return $next($request);
    }
}
