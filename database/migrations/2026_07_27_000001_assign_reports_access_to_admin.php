<?php

use App\Enums\ModuloSistemaEnum;
use App\Enums\RolSistemaEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permisos')->updateOrInsert(
            ['slug' => 'reportes.access'],
            [
                'nombre' => 'Acceso Reportes',
                'descripcion' => 'Permite acceder a reportes operativos de facturacion.',
                'modulo' => ModuloSistemaEnum::REPORTES->value,
                'estado' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $roleId = DB::table('roles')->where('slug', RolSistemaEnum::ADMINISTRADOR->value)->value('id');
        $permissionId = DB::table('permisos')->where('slug', 'reportes.access')->value('id');

        if (! $roleId || ! $permissionId) {
            return;
        }

        DB::table('permission_role')->updateOrInsert(
            ['role_id' => $roleId, 'permission_id' => $permissionId],
            ['created_at' => now(), 'updated_at' => now()],
        );
    }

    public function down(): void
    {
        $roleId = DB::table('roles')->where('slug', RolSistemaEnum::ADMINISTRADOR->value)->value('id');
        $permissionId = DB::table('permisos')->where('slug', 'reportes.access')->value('id');

        if ($roleId && $permissionId) {
            DB::table('permission_role')
                ->where('role_id', $roleId)
                ->where('permission_id', $permissionId)
                ->delete();
        }
    }
};
