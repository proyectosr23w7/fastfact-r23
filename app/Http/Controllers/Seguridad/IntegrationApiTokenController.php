<?php

namespace App\Http\Controllers\Seguridad;

use App\Enums\RolSistemaEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\IntegrationApiTokenResource;
use App\Http\Resources\UsuarioResource;
use App\Models\IntegrationApiToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class IntegrationApiTokenController extends Controller
{
    private const API_ROLES = [
        RolSistemaEnum::ADMINISTRADOR->value,
        RolSistemaEnum::CAJERO->value,
    ];

    private const CENTRALIZATION_ROLES = [
        RolSistemaEnum::SUPERADMIN->value,
    ];

    private const DEFAULT_ABILITIES = [
        'integracion.productos.manage',
        'integracion.clientes.manage',
        'integracion.facturas.emitir',
        'integracion.facturas.anular',
        'integracion.facturas.revertir',
        'integracion.cuis.manage',
        'integracion.cufd.manage',
    ];

    private const CENTRALIZATION_ABILITIES = [
        'centralizacion.estado',
        'centralizacion.backups',
    ];

    public function index(Request $request): JsonResponse
    {
        $tokens = IntegrationApiToken::query()
            ->with('user.roles.permissions')
            ->latest()
            ->get();

        $rolesPermitidos = self::API_ROLES;

        if ($request->user()?->hasRole(RolSistemaEnum::SUPERADMIN->value)) {
            $rolesPermitidos = array_merge($rolesPermitidos, self::CENTRALIZATION_ROLES);
        }

        $apiUsers = User::query()
            ->where('estado', true)
            ->whereHas('roles', fn ($query) => $query->whereIn('slug', $rolesPermitidos))
            ->with('roles.permissions')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Tokens de integracion obtenidos correctamente.',
            'data' => IntegrationApiTokenResource::collection($tokens)->resolve(),
            'meta' => [
                'abilities' => $request->user()?->hasRole(RolSistemaEnum::SUPERADMIN->value)
                    ? array_merge(self::DEFAULT_ABILITIES, self::CENTRALIZATION_ABILITIES)
                    : self::DEFAULT_ABILITIES,
                'users' => UsuarioResource::collection($apiUsers)->resolve(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('estado', true)],
            'name' => ['required', 'string', 'max:120'],
            'abilities' => ['nullable', 'array', 'min:1'],
            'abilities.*' => ['required', 'string', Rule::in(array_merge(self::DEFAULT_ABILITIES, self::CENTRALIZATION_ABILITIES))],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $user = User::query()->with('roles')->findOrFail($data['user_id']);
        $abilities = $data['abilities'] ?? self::DEFAULT_ABILITIES;
        $usesCentralization = collect($abilities)->contains(fn (string $ability) => in_array($ability, self::CENTRALIZATION_ABILITIES, true));

        if ($usesCentralization) {
            abort_unless(
                $request->user()?->hasRole(RolSistemaEnum::SUPERADMIN->value)
                && $user->roles->contains(fn ($role) => in_array($role->slug, self::CENTRALIZATION_ROLES, true)),
                422,
                'Los permisos API de centralizacion solo pueden asignarse a usuarios superadministradores.',
            );
        } else {
            abort_unless(
                $user->roles->contains(fn ($role) => in_array($role->slug, array_merge(self::API_ROLES, self::CENTRALIZATION_ROLES), true)),
                422,
                'Solo se pueden generar tokens API para usuarios con rol administrador, cajero o superadministrador.',
            );
        }

        [$token, $plainTextToken] = IntegrationApiToken::issueFor(
            $user,
            $data['name'],
            $abilities,
            isset($data['expires_at']) ? new \DateTimeImmutable($data['expires_at']) : null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Token de integracion generado correctamente. Copialo ahora; no volvera a mostrarse.',
            'data' => [
                'token' => IntegrationApiTokenResource::make($token->load('user.roles.permissions'))->resolve(),
                'plain_text_token' => $plainTextToken,
            ],
        ], 201);
    }

    public function revoke(IntegrationApiToken $token): JsonResponse
    {
        if ($token->revoked_at === null) {
            $token->forceFill(['revoked_at' => now()])->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Token de integracion revocado correctamente.',
            'data' => IntegrationApiTokenResource::make($token->refresh()->load('user.roles.permissions'))->resolve(),
        ]);
    }
}
