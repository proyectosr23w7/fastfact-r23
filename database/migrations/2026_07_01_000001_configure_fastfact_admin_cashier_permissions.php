<?php

use App\Enums\ModuloSistemaEnum;
use App\Enums\RolSistemaEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            ['nombre' => 'Ver facturas', 'slug' => 'facturacion.facturas.view', 'modulo' => ModuloSistemaEnum::FACTURACION->value],
            ['nombre' => 'Emitir facturas', 'slug' => 'facturacion.facturas.emitir', 'modulo' => ModuloSistemaEnum::FACTURACION->value],
            ['nombre' => 'Gestionar productos facturables', 'slug' => 'facturacion.productos.manage', 'modulo' => ModuloSistemaEnum::FACTURACION->value],
            ['nombre' => 'Gestionar clientes', 'slug' => 'ventas.clientes.manage', 'modulo' => ModuloSistemaEnum::VENTAS->value],
            ['nombre' => 'Gestionar catalogos operativos SIAT', 'slug' => 'facturacion.catalogos.manage', 'modulo' => ModuloSistemaEnum::FACTURACION->value],
            ['nombre' => 'Sincronizar catalogos SIAT', 'slug' => 'facturacion.siat.sync', 'modulo' => ModuloSistemaEnum::FACTURACION->value],
        ];

        foreach ($permissions as $permission) {
            DB::table('permisos')->updateOrInsert(
                ['slug' => $permission['slug']],
                [
                    'nombre' => $permission['nombre'],
                    'modulo' => $permission['modulo'],
                    'descripcion' => 'Permiso FastFact R23 para '.$permission['nombre'].'.',
                    'estado' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }

        $this->upsertRole(RolSistemaEnum::ADMINISTRADOR->value, RolSistemaEnum::ADMINISTRADOR->label(), 'Administra operacion comercial sin acceso a parametros ni sincronizacion SIAT.');
        $this->upsertRole(RolSistemaEnum::CAJERO->value, RolSistemaEnum::CAJERO->label(), 'Crea clientes, productos y emite facturas.');

        $this->syncRolePermissions(RolSistemaEnum::ADMINISTRADOR->value, [
            'facturacion.access',
            'facturacion.facturas.view',
            'facturacion.facturas.emitir',
            'facturacion.productos.manage',
            'ventas.clientes.manage',
            'facturacion.catalogos.manage',
        ]);

        $this->syncRolePermissions(RolSistemaEnum::CAJERO->value, [
            'facturacion.access',
            'facturacion.facturas.view',
            'facturacion.facturas.emitir',
            'facturacion.productos.manage',
            'ventas.clientes.manage',
        ]);

        $adminId = $this->upsertUser('Administrador FastFact', 'administrador@fastfact-r23.local');
        $cashierId = $this->upsertUser('Cajero FastFact', 'cajero@fastfact-r23.local');

        $this->assignRole($adminId, RolSistemaEnum::ADMINISTRADOR->value);
        $this->assignRole($cashierId, RolSistemaEnum::CAJERO->value);
    }

    public function down(): void
    {
        $roleIds = DB::table('roles')
            ->whereIn('slug', [RolSistemaEnum::ADMINISTRADOR->value, RolSistemaEnum::CAJERO->value])
            ->pluck('id');

        if ($roleIds->isNotEmpty()) {
            DB::table('permission_role')->whereIn('role_id', $roleIds)->delete();
            DB::table('role_user')->whereIn('role_id', $roleIds)->delete();
        }

        DB::table('users')
            ->whereIn('email', ['administrador@fastfact-r23.local', 'cajero@fastfact-r23.local'])
            ->delete();

        DB::table('roles')
            ->where('slug', RolSistemaEnum::CAJERO->value)
            ->delete();
    }

    private function upsertRole(string $slug, string $name, string $description): void
    {
        DB::table('roles')->updateOrInsert(
            ['slug' => $slug],
            [
                'nombre' => $name,
                'descripcion' => $description,
                'estado' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    private function syncRolePermissions(string $roleSlug, array $permissionSlugs): void
    {
        $roleId = DB::table('roles')->where('slug', $roleSlug)->value('id');
        $permissionIds = DB::table('permisos')->whereIn('slug', $permissionSlugs)->pluck('id');

        if (! $roleId) {
            return;
        }

        DB::table('permission_role')->where('role_id', $roleId)->delete();

        foreach ($permissionIds as $permissionId) {
            DB::table('permission_role')->updateOrInsert(
                ['role_id' => $roleId, 'permission_id' => $permissionId],
                ['created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    private function upsertUser(string $name, string $email): int
    {
        DB::table('users')->updateOrInsert(
            ['email' => $email],
            [
                'name' => $name,
                'password' => bcrypt('12345678'),
                'estado' => true,
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        return (int) DB::table('users')->where('email', $email)->value('id');
    }

    private function assignRole(int $userId, string $roleSlug): void
    {
        $roleId = DB::table('roles')->where('slug', $roleSlug)->value('id');

        if (! $userId || ! $roleId) {
            return;
        }

        DB::table('role_user')->updateOrInsert(
            ['user_id' => $userId, 'role_id' => $roleId],
            ['created_at' => now(), 'updated_at' => now()],
        );
    }
};
