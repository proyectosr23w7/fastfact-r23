<?php

namespace App\Repositories\Seguridad;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class UsuarioRepository
{
    public function allForIndex(): Collection
    {
        return User::query()
            ->with([
                'roles:id,nombre,slug',
                'roles.permissions:id,nombre,slug,modulo,estado',
            ])
            ->orderBy('name')
            ->get();
    }

    public function create(array $data): User
    {
        return User::query()->create($data);
    }

    public function update(User $user, array $data): User
    {
        $user->update($data);

        return $this->refresh($user);
    }

    public function syncRoles(User $user, array $roleIds): User
    {
        $user->roles()->sync($roleIds);

        return $this->refresh($user);
    }

    public function refresh(User $user): User
    {
        return $user->refresh()->load([
            'roles:id,nombre,slug',
            'roles.permissions:id,nombre,slug,modulo,estado',
        ]);
    }
}
