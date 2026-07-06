<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('configuraciones')) {
            return;
        }

        Schema::table('configuraciones', function (Blueprint $table) {
            if (! Schema::hasColumn('configuraciones', 'confirmacion_rapida_ventas')) {
                $table->boolean('confirmacion_rapida_ventas')->default(false)->after('pagos_credito_habilitados');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('configuraciones')) {
            return;
        }

        Schema::table('configuraciones', function (Blueprint $table) {
            if (Schema::hasColumn('configuraciones', 'confirmacion_rapida_ventas')) {
                $table->dropColumn('confirmacion_rapida_ventas');
            }
        });
    }
};
