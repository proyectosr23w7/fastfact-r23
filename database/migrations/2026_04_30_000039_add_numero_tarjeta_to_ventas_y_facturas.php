<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venta_cabeceras', function (Blueprint $table) {
            $table->string('numero_tarjeta', 32)->nullable()->after('codigo_metodo_pago');
        });

        Schema::table('facturas', function (Blueprint $table) {
            $table->string('numero_tarjeta', 32)->nullable()->after('codigo_metodo_pago');
        });
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropColumn('numero_tarjeta');
        });

        Schema::table('venta_cabeceras', function (Blueprint $table) {
            $table->dropColumn('numero_tarjeta');
        });
    }
};
