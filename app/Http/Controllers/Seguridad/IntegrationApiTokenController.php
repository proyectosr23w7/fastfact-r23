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

    private const DEFAULT_ABILITIES = [
        'integracion.productos.manage',
        'integracion.clientes.manage',
        'integracion.facturas.emitir',
        'integracion.facturas.anular',
        'integracion.facturas.revertir',
        'integracion.cuis.manage',
        'integracion.cufd.manage',
    ];

    public function index(): JsonResponse
    {
        $tokens = IntegrationApiToken::query()
            ->with('user.roles.permissions')
            ->latest()
            ->get();

        $apiUsers = User::query()
            ->where('estado', true)
            ->whereHas('roles', fn ($query) => $query->whereIn('slug', self::API_ROLES))
            ->with('roles.permissions')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Tokens de integracion obtenidos correctamente.',
            'data' => IntegrationApiTokenResource::collection($tokens)->resolve(),
            'meta' => [
                'abilities' => self::DEFAULT_ABILITIES,
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
            'abilities.*' => ['required', 'string', Rule::in(self::DEFAULT_ABILITIES)],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $user = User::query()->with('roles')->findOrFail($data['user_id']);

        abort_unless(
            $user->roles->contains(fn ($role) => in_array($role->slug, self::API_ROLES, true)),
            422,
            'Solo se pueden generar tokens API para usuarios con rol administrador o cajero.',
        );

        [$token, $plainTextToken] = IntegrationApiToken::issueFor(
            $user,
            $data['name'],
            $data['abilities'] ?? self::DEFAULT_ABILITIES,
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
