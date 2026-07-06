<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('empresas') && Schema::hasColumn('empresas', 'titular') && !Schema::hasColumn('empresas', 'propietario')) {
            Schema::table('empresas', function (Blueprint $table) {
                $table->renameColumn('titular', 'propietario');
            });
        }

        Schema::table('empresas', function (Blueprint $table) {
            if (!Schema::hasColumn('empresas', 'razon_social')) {
                $table->string('razon_social')->nullable()->after('nombre_empresa');
            }
            if (!Schema::hasColumn('empresas', 'direccion')) {
                $table->string('direccion')->nullable()->after('propietario');
            }
            if (!Schema::hasColumn('empresas', 'telefono')) {
                $table->string('telefono')->nullable()->after('direccion');
            }
            if (!Schema::hasColumn('empresas', 'correo')) {
                $table->string('correo')->nullable()->after('telefono');
            }
            if (!Schema::hasColumn('empresas', 'logo')) {
                $table->string('logo')->nullable()->after('correo');
            }
            if (!Schema::hasColumn('empresas', 'estado')) {
                $table->boolean('estado')->default(true)->after('logo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            foreach (['razon_social', 'direccion', 'telefono', 'correo', 'logo', 'estado'] as $column) {
                if (Schema::hasColumn('empresas', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        if (Schema::hasTable('empresas') && Schema::hasColumn('empresas', 'propietario') && !Schema::hasColumn('empresas', 'titular')) {
            Schema::table('empresas', function (Blueprint $table) {
                $table->renameColumn('propietario', 'titular');
            });
        }
    }
};
