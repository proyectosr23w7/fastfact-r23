<?php

namespace App\Services\Seguridad;

use App\Enums\RolSistemaEnum;
use App\Models\User;
use App\Repositories\Seguridad\UsuarioRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UsuarioService
{
    public function __construct(
        private readonly UsuarioRepository $repository,
    ) {
    }

    public function listar(): Collection
    {
        return $this->repository->allForIndex();
    }

    public function crear(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $roleIds = Arr::pull($data, 'role_ids', []);
            $data['estado'] = $data['estado'] ?? true;

            $user = $this->repository->create($data);

            if ($roleIds !== []) {
                $this->ensureSuperadminIsProtected($user, $roleIds);
                $user = $this->repository->syncRoles($user, $roleIds);
            }

            return $this->repository->refresh($user);
        });
    }

    public function actualizar(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $roleIds = Arr::pull($data, 'role_ids', null);
            if (empty($data['password'])) {
                unset($data['password']);
            }

            $user = $this->repository->update($user, $data);

            if (is_array($roleIds)) {
                $this->ensureSuperadminIsProtected($user, $roleIds);
                $user = $this->repository->syncRoles($user, $roleIds);
            }

            return $this->repository->refresh($user);
        });
    }

    public function cambiarEstado(User $user, bool $estado, ?int $actorId = null): User
    {
        if (! $estado && $actorId === $user->id) {
            throw ValidationException::withMessages([
                'estado' => 'No puedes desactivar tu propio usuario mientras tienes una sesión activa.',
            ]);
        }

        if ($this->isDefaultSuperadmin($user) && ! $estado) {
            throw ValidationException::withMessages([
                'estado' => 'El usuario superadmin predeterminado no puede deshabilitarse.',
            ]);
        }

        return $this->repository->update($user, ['estado' => $estado]);
    }

    public function asignarRoles(User $user, array $roleIds): User
    {
        $this->ensureSuperadminIsProtected($user, $roleIds);

        return $this->repository->syncRoles($user, $roleIds);
    }

    public function asignarAcceso(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $roleIds = Arr::pull($data, 'role_ids');
            $this->ensureSuperadminIsProtected($user, $roleIds);
            $user = $this->repository->syncRoles($user, $roleIds);

            return $this->repository->refresh($user);
        });
    }

    private function ensureSuperadminIsProtected(User $user, array $roleIds): void
    {
        if (! $this->isDefaultSuperadmin($user)) {
            return;
        }

        $hasSuperadmin = $user->roles()
            ->whereIn('roles.id', $roleIds)
            ->where('slug', RolSistemaEnum::SUPERADMIN->value)
            ->exists();

        if ($hasSuperadmin) {
            return;
        }

        throw ValidationException::withMessages([
            'role_ids' => 'El usuario superadmin predeterminado debe conservar el rol superadmin.',
        ]);
    }

    private function isDefaultSuperadmin(User $user): bool
    {
        return $user->email === 'proyectosr23w7@gmail.com';
    }
}
