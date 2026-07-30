<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sucursales')) {
            return;
        }

        if (! Schema::hasColumn('sucursales', 'municipio')) {
            Schema::table('sucursales', function (Blueprint $table) {
                $table->string('municipio', 100)->nullable()->after('nombre');
            });
        }

        DB::table('sucursales')
            ->where(function ($query) {
                $query->whereNull('municipio')
                    ->orWhere('municipio', '');
            })
            ->update(['municipio' => 'LA PAZ']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('sucursales') || ! Schema::hasColumn('sucursales', 'municipio')) {
            return;
        }

        Schema::table('sucursales', function (Blueprint $table) {
            $table->dropColumn('municipio');
        });
    }
};
