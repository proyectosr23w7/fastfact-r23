<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('configuraciones')
            ->where('tipo_impresion', 'carta')
            ->update(['tipo_impresion' => 'media_carta']);
    }

    public function down(): void
    {
        DB::table('configuraciones')
            ->where('tipo_impresion', 'media_carta')
            ->update(['tipo_impresion' => 'carta']);
    }
};
