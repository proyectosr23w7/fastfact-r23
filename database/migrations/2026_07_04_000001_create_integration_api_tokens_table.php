<?php

use App\Enums\ModuloSistemaEnum;
use App\Enums\RolSistemaEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_api_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('token_hash', 64)->unique();
            $table->json('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        $permissions = [
            ['nombre' => 'API integracion productos', 'slug' => 'integracion.productos.manage'],
            ['nombre' => 'API integracion clientes', 'slug' => 'integracion.clientes.manage'],
            ['nombre' => 'API integracion emitir facturas', 'slug' => 'integracion.facturas.emitir'],
            ['nombre' => 'API integracion anular facturas', 'slug' => 'integracion.facturas.anular'],
            ['nombre' => 'API integracion revertir facturas', 'slug' => 'integracion.facturas.revertir'],
            ['nombre' => 'API integracion CUFD', 'slug' => 'integracion.cufd.manage'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permisos')->updateOrInsert(
                ['slug' => $permission['slug']],
                [
                    'nombre' => $permission['nombre'],
                    'modulo' => ModuloSistemaEnum::FACTURACION->value,
                    'descripcion' => 'Permiso explicito para la API externa de FastFact R23.',
                    'estado' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }

        $adminRoleId = DB::table('roles')
            ->where('slug', RolSistemaEnum::ADMINISTRADOR->value)
            ->value('id');

        if ($adminRoleId) {
            $permissionIds = DB::table('permisos')
                ->whereIn('slug', array_column($permissions, 'slug'))
                ->pluck('id');

            foreach ($permissionIds as $permissionId) {
                DB::table('permission_role')->updateOrInsert(
                    ['role_id' => $adminRoleId, 'permission_id' => $permissionId],
                    ['created_at' => now(), 'updated_at' => now()],
                );
            }
        }
    }

    public function down(): void
    {
        $slugs = [
            'integracion.productos.manage',
            'integracion.clientes.manage',
            'integracion.facturas.emitir',
            'integracion.facturas.anular',
            'integracion.facturas.revertir',
            'integracion.cufd.manage',
        ];

        $permissionIds = DB::table('permisos')->whereIn('slug', $slugs)->pluck('id');

        if ($permissionIds->isNotEmpty()) {
            DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        }

        DB::table('permisos')->whereIn('slug', $slugs)->delete();
        Schema::dropIfExists('integration_api_tokens');
    }
};
