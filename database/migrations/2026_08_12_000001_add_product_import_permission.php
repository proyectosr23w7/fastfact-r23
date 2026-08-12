<?php

use App\Enums\ModuloSistemaEnum;
use App\Enums\RolSistemaEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private string $permissionSlug = 'facturacion.productos.import';

    public function up(): void
    {
        DB::table('permisos')->updateOrInsert(
            ['slug' => $this->permissionSlug],
            [
                'nombre' => 'Importar productos',
                'descripcion' => 'Permite importar productos desde Excel y asignar homologacion SIAT masivamente.',
                'modulo' => ModuloSistemaEnum::FACTURACION->value,
                'estado' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $roleId = DB::table('roles')
            ->where('slug', RolSistemaEnum::SUPERADMIN->value)
            ->value('id');
        $permissionId = DB::table('permisos')
            ->where('slug', $this->permissionSlug)
            ->value('id');

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
        $permissionId = DB::table('permisos')
            ->where('slug', $this->permissionSlug)
            ->value('id');

        if ($permissionId) {
            DB::table('permission_role')
                ->where('permission_id', $permissionId)
                ->delete();
        }
    }
};
