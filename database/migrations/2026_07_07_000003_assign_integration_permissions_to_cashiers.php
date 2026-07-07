<?php

use App\Enums\RolSistemaEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $permissionSlugs = [
        'integracion.productos.manage',
        'integracion.clientes.manage',
        'integracion.facturas.emitir',
        'integracion.facturas.anular',
        'integracion.facturas.revertir',
        'integracion.cuis.manage',
        'integracion.cufd.manage',
    ];

    public function up(): void
    {
        $cashierRoleId = DB::table('roles')
            ->where('slug', RolSistemaEnum::CAJERO->value)
            ->value('id');

        if (! $cashierRoleId) {
            return;
        }

        $permissionIds = DB::table('permisos')
            ->whereIn('slug', $this->permissionSlugs)
            ->pluck('id');

        foreach ($permissionIds as $permissionId) {
            DB::table('permission_role')->updateOrInsert(
                ['role_id' => $cashierRoleId, 'permission_id' => $permissionId],
                ['created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    public function down(): void
    {
        $cashierRoleId = DB::table('roles')
            ->where('slug', RolSistemaEnum::CAJERO->value)
            ->value('id');

        if (! $cashierRoleId) {
            return;
        }

        $permissionIds = DB::table('permisos')
            ->whereIn('slug', $this->permissionSlugs)
            ->pluck('id');

        DB::table('permission_role')
            ->where('role_id', $cashierRoleId)
            ->whereIn('permission_id', $permissionIds)
            ->delete();
    }
};
