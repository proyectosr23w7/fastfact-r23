<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venta_cabeceras', function (Blueprint $table) {
            $table->decimal('monto_gift_card', 18, 2)->nullable()->after('numero_tarjeta');
        });

        Schema::table('facturas', function (Blueprint $table) {
            $table->decimal('monto_gift_card', 18, 2)->nullable()->after('numero_tarjeta');
        });
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropColumn('monto_gift_card');
        });

        Schema::table('venta_cabeceras', function (Blueprint $table) {
            $table->dropColumn('monto_gift_card');
        });
    }
};
