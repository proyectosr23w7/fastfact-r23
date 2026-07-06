<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sin_unidades_medida') || Schema::hasColumn('sin_unidades_medida', 'habilitado_uso')) {
            return;
        }

        Schema::table('sin_unidades_medida', function (Blueprint $table) {
            $table->boolean('habilitado_uso')->default(true)->after('estado');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('sin_unidades_medida') || ! Schema::hasColumn('sin_unidades_medida', 'habilitado_uso')) {
            return;
        }

        Schema::table('sin_unidades_medida', function (Blueprint $table) {
            $table->dropColumn('habilitado_uso');
        });
    }
};
