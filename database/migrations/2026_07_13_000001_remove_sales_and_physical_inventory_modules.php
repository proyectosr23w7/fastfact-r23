<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('facturas') && Schema::hasColumn('facturas', 'venta_id')) {
            try {
                Schema::table('facturas', function (Blueprint $table): void {
                    $table->dropForeign(['venta_id']);
                });
            } catch (Throwable) {
                // Algunas bases antiguas no tienen la restriccion con el nombre convencional.
            }

            if (DB::connection()->getDriverName() === 'sqlite') {
                try {
                    Schema::table('facturas', function (Blueprint $table): void {
                        $table->dropUnique('facturas_venta_id_unique');
                    });
                } catch (Throwable) {
                    // El indice puede no existir en instalaciones creadas con otro esquema.
                }
            }

            Schema::table('facturas', function (Blueprint $table): void {
                $table->dropColumn('venta_id');
            });
        }

        Schema::dropIfExists('venta_detalle_lotes');
        Schema::dropIfExists('venta_detalles');
        Schema::dropIfExists('venta_cabeceras');
        Schema::dropIfExists('articulo_stocks');
        Schema::dropIfExists('articulo_lotes');
        Schema::dropIfExists('kardex');

        if (Schema::hasTable('permisos')) {
            DB::table('permisos')
                ->where('slug', 'ventas.access')
                ->delete();
        }
    }

    public function down(): void
    {
        throw new LogicException(
            'Esta limpieza elimina datos de ventas e inventario fisico. Restaure un respaldo para revertirla.',
        );
    }
};
