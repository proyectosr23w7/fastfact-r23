<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'estado')) {
                $table->boolean('estado')->default(true)->after('password');
            }
        });

        if (Schema::hasColumn('users', 'activo')) {
            DB::table('users')->update([
                'estado' => DB::raw('coalesce(activo, 1)'),
            ]);
        }

        Schema::table('roles', function (Blueprint $table) {
            if (! Schema::hasColumn('roles', 'slug')) {
                $table->string('slug')->nullable()->after('nombre');
            }
            if (! Schema::hasColumn('roles', 'estado')) {
                $table->boolean('estado')->default(true)->after('descripcion');
            }
        });

        Schema::table('permisos', function (Blueprint $table) {
            if (! Schema::hasColumn('permisos', 'nombre')) {
                $table->string('nombre')->nullable()->after('id');
            }
            if (! Schema::hasColumn('permisos', 'slug')) {
                $table->string('slug')->nullable()->after('nombre');
            }
            if (! Schema::hasColumn('permisos', 'estado')) {
                $table->boolean('estado')->default(true)->after('descripcion');
            }
        });

        DB::table('roles')
            ->orderBy('id')
            ->get()
            ->each(function (object $role): void {
                DB::table('roles')
                    ->where('id', $role->id)
                    ->update([
                        'slug' => $role->slug ?: Str::slug((string) $role->nombre),
                    ]);
            });

        DB::table('permisos')
            ->orderBy('id')
            ->get()
            ->each(function (object $permission): void {
                $nombre = $permission->nombre ?: $permission->descripcion ?: 'permiso-'.$permission->id;

                DB::table('permisos')
                    ->where('id', $permission->id)
                    ->update([
                        'nombre' => $nombre,
                        'slug' => $permission->slug ?: Str::slug($nombre),
                    ]);
            });

        if (Schema::hasTable('user_role') && ! Schema::hasTable('role_user')) {
            Schema::create('role_user', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['user_id', 'role_id']);
            });

            $rows = DB::table('user_role')->get();
            foreach ($rows as $row) {
                DB::table('role_user')->updateOrInsert(
                    ['user_id' => $row->user_id, 'role_id' => $row->role_id],
                    ['created_at' => now(), 'updated_at' => now()],
                );
            }
        }

        if (! Schema::hasTable('permission_role')) {
            Schema::create('permission_role', function (Blueprint $table) {
                $table->id();
                $table->foreignId('permission_id')->constrained('permisos')->cascadeOnDelete();
                $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['permission_id', 'role_id']);
            });

            if (Schema::hasTable('role_permiso')) {
                $rows = DB::table('role_permiso')->get();
                foreach ($rows as $row) {
                    DB::table('permission_role')->updateOrInsert(
                        ['permission_id' => $row->permiso_id, 'role_id' => $row->role_id],
                        ['created_at' => now(), 'updated_at' => now()],
                    );
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('permission_role')) {
            Schema::drop('permission_role');
        }

        if (Schema::hasTable('role_user')) {
            Schema::drop('role_user');
        }

        Schema::table('permisos', function (Blueprint $table) {
            foreach (['nombre', 'slug', 'estado'] as $column) {
                if (Schema::hasColumn('permisos', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('roles', function (Blueprint $table) {
            foreach (['slug', 'estado'] as $column) {
                if (Schema::hasColumn('roles', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'estado')) {
                $table->dropColumn('estado');
            }
        });
    }
};
