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

        Schema::table('configuraciones', function (Blueprint $table): void {
            if (! Schema::hasColumn('configuraciones', 'mostrar_descuento_detalle_factura')) {
                $table->boolean('mostrar_descuento_detalle_factura')->default(true)->after('precios_por_cantidad');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('configuraciones')) {
            return;
        }

        Schema::table('configuraciones', function (Blueprint $table): void {
            if (Schema::hasColumn('configuraciones', 'mostrar_descuento_detalle_factura')) {
                $table->dropColumn('mostrar_descuento_detalle_factura');
            }
        });
    }
};
