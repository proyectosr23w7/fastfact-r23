<?php

namespace App\Http\Middleware;

use App\Enums\RolSistemaEnum;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperadmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user, 401);
        abort_unless(
            $user->hasRole(RolSistemaEnum::SUPERADMIN->value),
            403,
            'Solo el superadministrador puede acceder a este servicio.',
        );

        return $next($request);
    }
}
