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
            if (! Schema::hasColumn('configuraciones', 'pagos_credito_habilitados')) {
                $table->boolean('pagos_credito_habilitados')->default(false)->after('precios_por_cantidad');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('configuraciones')) {
            return;
        }

        Schema::table('configuraciones', function (Blueprint $table) {
            if (Schema::hasColumn('configuraciones', 'pagos_credito_habilitados')) {
                $table->dropColumn('pagos_credito_habilitados');
            }
        });
    }
};
