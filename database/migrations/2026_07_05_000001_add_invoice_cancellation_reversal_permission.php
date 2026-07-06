<?php

use App\Enums\ModuloSistemaEnum;
use App\Enums\RolSistemaEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private string $permissionSlug = 'facturacion.facturas.revertir_anulacion';

    public function up(): void
    {
        DB::table('permisos')->updateOrInsert(
            ['slug' => $this->permissionSlug],
            [
                'nombre' => 'Revertir anulacion de facturas',
                'modulo' => ModuloSistemaEnum::FACTURACION->value,
                'descripcion' => 'Permite ejecutar la reversion de anulacion de facturas aceptadas por SIAT.',
                'estado' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $adminRoleId = DB::table('roles')
            ->where('slug', RolSistemaEnum::ADMINISTRADOR->value)
            ->value('id');

        $permissionId = DB::table('permisos')
            ->where('slug', $this->permissionSlug)
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
            ->where('slug', $this->permissionSlug)
            ->value('id');

        if ($permissionId) {
            DB::table('permission_role')->where('permission_id', $permissionId)->delete();
        }

        DB::table('permisos')->where('slug', $this->permissionSlug)->delete();
    }
};
