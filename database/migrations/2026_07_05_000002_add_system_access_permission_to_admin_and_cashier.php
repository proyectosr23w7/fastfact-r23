<?php

use App\Enums\ModuloSistemaEnum;
use App\Enums\RolSistemaEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private string $permissionSlug = 'sistema.access';

    public function up(): void
    {
        DB::table('permisos')->updateOrInsert(
            ['slug' => $this->permissionSlug],
            [
                'nombre' => 'Acceso al sistema',
                'modulo' => ModuloSistemaEnum::SEGURIDAD->value,
                'descripcion' => 'Permite ingresar al entorno privado de FastFact R23W7.',
                'estado' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $permissionId = DB::table('permisos')
            ->where('slug', $this->permissionSlug)
            ->value('id');

        if (! $permissionId) {
            return;
        }

        $roleIds = DB::table('roles')
            ->whereIn('slug', [
                RolSistemaEnum::ADMINISTRADOR->value,
                RolSistemaEnum::CAJERO->value,
            ])
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            DB::table('permission_role')->updateOrInsert(
                ['role_id' => $roleId, 'permission_id' => $permissionId],
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
