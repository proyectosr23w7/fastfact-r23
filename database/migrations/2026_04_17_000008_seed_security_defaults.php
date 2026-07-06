<?php

use App\Enums\ModuloSistemaEnum;
use App\Enums\RolSistemaEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $defaultPermissions = [
            ['nombre' => 'Ver Empresa', 'slug' => 'configuracion.empresa.view', 'modulo' => ModuloSistemaEnum::CONFIGURACION->value],
            ['nombre' => 'Gestionar Configuracion', 'slug' => 'configuracion.general.manage', 'modulo' => ModuloSistemaEnum::CONFIGURACION->value],
            ['nombre' => 'Gestionar Sucursales', 'slug' => 'configuracion.sucursales.manage', 'modulo' => ModuloSistemaEnum::CONFIGURACION->value],
            ['nombre' => 'Gestionar Puntos de Venta', 'slug' => 'configuracion.puntos_venta.manage', 'modulo' => ModuloSistemaEnum::CONFIGURACION->value],
            ['nombre' => 'Gestionar Usuarios', 'slug' => 'seguridad.usuarios.manage', 'modulo' => ModuloSistemaEnum::SEGURIDAD->value],
            ['nombre' => 'Gestionar Roles', 'slug' => 'seguridad.roles.manage', 'modulo' => ModuloSistemaEnum::SEGURIDAD->value],
            ['nombre' => 'Gestionar Permisos', 'slug' => 'seguridad.permisos.manage', 'modulo' => ModuloSistemaEnum::SEGURIDAD->value],
            ['nombre' => 'Acceso Inventario', 'slug' => 'inventario.access', 'modulo' => ModuloSistemaEnum::INVENTARIO->value],
            ['nombre' => 'Acceso Ventas', 'slug' => 'ventas.access', 'modulo' => ModuloSistemaEnum::VENTAS->value],
            ['nombre' => 'Acceso Reportes', 'slug' => 'reportes.access', 'modulo' => ModuloSistemaEnum::REPORTES->value],
            ['nombre' => 'Acceso Facturacion', 'slug' => 'facturacion.access', 'modulo' => ModuloSistemaEnum::FACTURACION->value],
        ];

        foreach ($defaultPermissions as $permission) {
            DB::table('permisos')->updateOrInsert(
                ['slug' => $permission['slug']],
                [
                    'nombre' => $permission['nombre'],
                    'modulo' => $permission['modulo'],
                    'descripcion' => 'Permiso base del sistema para '.$permission['nombre'].'.',
                    'estado' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }

        DB::table('roles')->updateOrInsert(
            ['slug' => RolSistemaEnum::SUPERADMIN->value],
            [
                'nombre' => RolSistemaEnum::SUPERADMIN->label(),
                'descripcion' => 'Rol con acceso total para la instalacion.',
                'estado' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        DB::table('roles')->updateOrInsert(
            ['slug' => RolSistemaEnum::ADMINISTRADOR->value],
            [
                'nombre' => RolSistemaEnum::ADMINISTRADOR->label(),
                'descripcion' => 'Rol administrativo base.',
                'estado' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $superAdminRoleId = DB::table('roles')
            ->where('slug', RolSistemaEnum::SUPERADMIN->value)
            ->value('id');

        $defaultUserId = DB::table('users')
            ->where('email', 'proyectosr23w7@gmail.com')
            ->value('id');

        if ($superAdminRoleId && $defaultUserId) {
            DB::table('role_user')->updateOrInsert(
                ['user_id' => $defaultUserId, 'role_id' => $superAdminRoleId],
                ['created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    public function down(): void
    {
        $roleIds = DB::table('roles')
            ->whereIn('slug', [
                RolSistemaEnum::SUPERADMIN->value,
                RolSistemaEnum::ADMINISTRADOR->value,
            ])
            ->pluck('id');

        if ($roleIds->isNotEmpty() && DB::getSchemaBuilder()->hasTable('role_user')) {
            DB::table('role_user')->whereIn('role_id', $roleIds)->delete();
        }

        DB::table('roles')
            ->whereIn('slug', [
                RolSistemaEnum::SUPERADMIN->value,
                RolSistemaEnum::ADMINISTRADOR->value,
            ])
            ->delete();

        DB::table('permisos')
            ->whereIn('slug', [
                'configuracion.empresa.view',
                'configuracion.general.manage',
                'configuracion.sucursales.manage',
                'configuracion.puntos_venta.manage',
                'seguridad.usuarios.manage',
                'seguridad.roles.manage',
                'seguridad.permisos.manage',
                'inventario.access',
                'ventas.access',
                'reportes.access',
                'facturacion.access',
            ])
            ->delete();
    }
};
