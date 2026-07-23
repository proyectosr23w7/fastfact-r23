<?php

use App\Enums\ModuloSistemaEnum;
use App\Enums\RolSistemaEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $permissions = [
        [
            'nombre' => 'Anular facturas',
            'slug' => 'facturacion.facturas.anular',
            'descripcion' => 'Permite ejecutar la anulacion de facturas aceptadas u observadas.',
        ],
        [
            'nombre' => 'Revertir anulacion de facturas',
            'slug' => 'facturacion.facturas.revertir_anulacion',
            'descripcion' => 'Permite ejecutar la reversion de anulacion de facturas aceptadas por SIAT.',
        ],
    ];

    public function up(): void
    {
        foreach ($this->permissions as $permission) {
            DB::table('permisos')->updateOrInsert(
                ['slug' => $permission['slug']],
                [
                    'nombre' => $permission['nombre'],
                    'descripcion' => $permission['descripcion'],
                    'modulo' => ModuloSistemaEnum::FACTURACION->value,
                    'estado' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }

        $adminRoleId = DB::table('roles')->where('slug', RolSistemaEnum::ADMINISTRADOR->value)->value('id');

        if (! $adminRoleId) {
            return;
        }

        $permissionIds = DB::table('permisos')
            ->whereIn('slug', array_column($this->permissions, 'slug'))
            ->pluck('id');

        foreach ($permissionIds as $permissionId) {
            DB::table('permission_role')->updateOrInsert(
                ['role_id' => $adminRoleId, 'permission_id' => $permissionId],
                ['created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permisos')
            ->whereIn('slug', array_column($this->permissions, 'slug'))
            ->pluck('id');

        if ($permissionIds->isNotEmpty()) {
            DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        }
    }
};
