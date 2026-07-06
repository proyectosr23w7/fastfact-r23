<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('configuraciones')) {
            return;
        }

        DB::table('configuraciones')
            ->whereIn('metodo_costos', ['promedio', 'peps', null])
            ->update([
                'metodo_costos' => 'promedio_ponderado',
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('configuraciones')) {
            return;
        }

        DB::table('configuraciones')
            ->where('metodo_costos', 'promedio_ponderado')
            ->update([
                'metodo_costos' => 'promedio',
            ]);
    }
};
