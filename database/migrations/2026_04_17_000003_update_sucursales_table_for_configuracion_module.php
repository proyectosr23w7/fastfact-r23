<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sucursales') && Schema::hasColumn('sucursales', 'codigo_sucursal') && !Schema::hasColumn('sucursales', 'codigo')) {
            Schema::table('sucursales', function (Blueprint $table) {
                $table->renameColumn('codigo_sucursal', 'codigo');
            });
        }

        if (Schema::hasTable('sucursales') && Schema::hasColumn('sucursales', 'activo') && !Schema::hasColumn('sucursales', 'estado')) {
            Schema::table('sucursales', function (Blueprint $table) {
                $table->renameColumn('activo', 'estado');
            });
        }

        if (Schema::hasTable('sucursales') && Schema::hasColumn('sucursales', 'es_global')) {
            Schema::table('sucursales', function (Blueprint $table) {
                $table->dropColumn('es_global');
            });
        }

        DB::table('sucursales')
            ->whereNull('codigo')
            ->update(['codigo' => 0]);
    }

    public function down(): void
    {
        if (Schema::hasTable('sucursales') && Schema::hasColumn('sucursales', 'codigo') && !Schema::hasColumn('sucursales', 'codigo_sucursal')) {
            Schema::table('sucursales', function (Blueprint $table) {
                $table->renameColumn('codigo', 'codigo_sucursal');
            });
        }

        if (Schema::hasTable('sucursales') && Schema::hasColumn('sucursales', 'estado') && !Schema::hasColumn('sucursales', 'activo')) {
            Schema::table('sucursales', function (Blueprint $table) {
                $table->renameColumn('estado', 'activo');
            });
        }

        if (Schema::hasTable('sucursales') && !Schema::hasColumn('sucursales', 'es_global')) {
            Schema::table('sucursales', function (Blueprint $table) {
                $table->boolean('es_global')->default(false);
            });
        }
    }
};
