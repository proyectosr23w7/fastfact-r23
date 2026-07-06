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
            ->whereIn('metodo_salida', ['impresion', 'pdf', 'ambos'])
            ->update(['metodo_salida' => 'peps']);
    }

    public function down(): void
    {
    }
};
