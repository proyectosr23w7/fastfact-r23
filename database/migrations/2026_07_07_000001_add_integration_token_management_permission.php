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
            ['slug' => 'integracion.tokens.manage'],
            [
                'nombre' => 'Gestionar tokens API de integracion',
                'modulo' => ModuloSistemaEnum::FACTURACION->value,
                'descripcion' => 'Permite crear y revocar tokens Bearer para la API externa.',
                'estado' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $adminRoleId = DB::table('roles')
            ->where('slug', RolSistemaEnum::ADMINISTRADOR->value)
            ->value('id');
        $permissionId = DB::table('permisos')
            ->where('slug', 'integracion.tokens.manage')
            ->value('id');

        if ($adminRoleId && $permissionId) {
            DB::table('permission_role')->updateOrInsert(
                ['role_id' => $adminRoleId, 'permission_id' => $permissionId],
                ['created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permisos')
            ->where('slug', 'integracion.tokens.manage')
            ->value('id');

        if ($permissionId) {
            DB::table('permission_role')->where('permission_id', $permissionId)->delete();
        }

        DB::table('permisos')->where('slug', 'integracion.tokens.manage')->delete();
    }
};
