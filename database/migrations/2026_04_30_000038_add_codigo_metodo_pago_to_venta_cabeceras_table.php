<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('venta_cabeceras') || Schema::hasColumn('venta_cabeceras', 'codigo_metodo_pago')) {
            return;
        }

        Schema::table('venta_cabeceras', function (Blueprint $table) {
            $table->string('codigo_metodo_pago', 20)->nullable()->after('tipo_documento_venta');
            $table->index('codigo_metodo_pago');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('venta_cabeceras') || ! Schema::hasColumn('venta_cabeceras', 'codigo_metodo_pago')) {
            return;
        }

        Schema::table('venta_cabeceras', function (Blueprint $table) {
            $table->dropIndex(['codigo_metodo_pago']);
            $table->dropColumn('codigo_metodo_pago');
        });
    }
};
