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
            ['nombre' => 'Ver clientes', 'slug' => 'ventas.clientes.view'],
            ['nombre' => 'Crear clientes', 'slug' => 'ventas.clientes.create'],
            ['nombre' => 'Editar clientes', 'slug' => 'ventas.clientes.edit'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permisos')->updateOrInsert(
                ['slug' => $permission['slug']],
                [
                    'nombre' => $permission['nombre'],
                    'modulo' => ModuloSistemaEnum::VENTAS->value,
                    'descripcion' => 'Permiso FastFact R23 para '.$permission['nombre'].'.',
                    'estado' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }

        foreach ([RolSistemaEnum::ADMINISTRADOR->value, RolSistemaEnum::CAJERO->value] as $roleSlug) {
            $roleId = DB::table('roles')->where('slug', $roleSlug)->value('id');
            $permissionIds = DB::table('permisos')
                ->whereIn('slug', ['ventas.clientes.manage', 'ventas.clientes.view', 'ventas.clientes.create', 'ventas.clientes.edit'])
                ->pluck('id');

            foreach ($permissionIds as $permissionId) {
                DB::table('permission_role')->updateOrInsert(
                    ['role_id' => $roleId, 'permission_id' => $permissionId],
                    ['created_at' => now(), 'updated_at' => now()],
                );
            }
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permisos')
            ->whereIn('slug', ['ventas.clientes.view', 'ventas.clientes.create', 'ventas.clientes.edit'])
            ->pluck('id');

        if ($permissionIds->isNotEmpty()) {
            DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
            DB::table('permisos')->whereIn('id', $permissionIds)->delete();
        }
    }
};