<?php

namespace App\Http\Middleware;

use App\Enums\RolSistemaEnum;
use App\Models\IntegrationApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIntegrationPermission
{
    private const API_ROLES = [
        RolSistemaEnum::ADMINISTRADOR->value,
        RolSistemaEnum::CAJERO->value,
    ];

    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();
        $token = $request->attributes->get('integration_token');

        abort_unless($token instanceof IntegrationApiToken, 401, 'Token de integracion requerido.');
        abort_unless(
            $user && collect(self::API_ROLES)->contains(fn (string $role) => $user->hasRole($role)),
            403,
            'La API de integracion solo acepta usuarios administradores o cajeros.',
        );

        if ($permissions === []) {
            return $next($request);
        }

        $explicitPermissions = $user->roles()
            ->whereIn('roles.slug', self::API_ROLES)
            ->with('permissions:id,slug,estado')
            ->get()
            ->flatMap(fn ($role) => $role->permissions)
            ->filter(fn ($permission) => (bool) $permission->estado)
            ->pluck('slug')
            ->all();

        $allowed = collect($permissions)->contains(
            fn (string $permission) => in_array($permission, $explicitPermissions, true) && $token->canUse($permission),
        );

        abort_unless($allowed, 403, 'El token no tiene permiso para esta operacion de integracion.');

        return $next($request);
    }
}
