<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('facturas') || Schema::hasColumn('facturas', 'leyenda')) {
            return;
        }

        Schema::table('facturas', function (Blueprint $table): void {
            $table->text('leyenda')->nullable()->after('codigo_excepcion');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('facturas') || ! Schema::hasColumn('facturas', 'leyenda')) {
            return;
        }

        Schema::table('facturas', function (Blueprint $table): void {
            $table->dropColumn('leyenda');
        });
    }
};
