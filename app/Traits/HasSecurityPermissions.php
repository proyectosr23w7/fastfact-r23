<?php

namespace App\Traits;

use App\Enums\RolSistemaEnum;
use App\Models\Permission;
use Illuminate\Support\Collection;

trait HasSecurityPermissions
{
    public function getAllPermissionsAttribute(): Collection
    {
        if ($this->relationLoaded('roles')) {
            $roles = $this->roles;
        } else {
            $roles = $this->roles()->with('permissions:id,nombre,slug,modulo,descripcion,estado')->get();
        }

        if ($roles->contains(fn ($role) => $role->slug === RolSistemaEnum::SUPERADMIN->value)) {
            return Permission::query()
                ->where('estado', true)
                ->orderBy('modulo')
                ->orderBy('nombre')
                ->get();
        }

        return $roles
            ->flatMap(fn ($role) => $role->permissions)
            ->unique('id')
            ->values();
    }

    public function getPermissionSlugsAttribute(): array
    {
        if ($this->roles->contains(fn ($role) => $role->slug === RolSistemaEnum::SUPERADMIN->value)) {
            return ['*'];
        }

        return $this->all_permissions
            ->pluck('slug')
            ->filter()
            ->values()
            ->all();
    }

    public function hasPermission(string $permissionSlug): bool
    {
        return in_array('*', $this->permission_slugs, true)
            || in_array($permissionSlug, $this->permission_slugs, true);
    }
}
