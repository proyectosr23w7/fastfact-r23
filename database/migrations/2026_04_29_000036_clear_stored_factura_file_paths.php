<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('facturas')) {
            return;
        }

        DB::table('facturas')->update([
            'xml_generado_path' => null,
            'xml_firmado_path' => null,
            'pdf_path' => null,
        ]);
    }

    public function down(): void
    {
        // No se restauran rutas historicas porque estos archivos ya no se persisten.
    }
};
