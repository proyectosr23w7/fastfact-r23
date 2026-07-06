<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->decimal('descuento_global', 14, 2)->default(0)->after('monto_gift_card');
        });

        if (Schema::hasColumn('venta_cabeceras', 'descuento') && DB::connection()->getDriverName() === 'sqlite') {
            DB::table('facturas')
                ->whereNotNull('venta_id')
                ->orderBy('id')
                ->lazyById()
                ->each(function (object $factura) {
                    $descuento = DB::table('venta_cabeceras')
                        ->where('id', $factura->venta_id)
                        ->value('descuento') ?? 0;

                    DB::table('facturas')
                        ->where('id', $factura->id)
                        ->update(['descuento_global' => $descuento]);
                });
        } elseif (Schema::hasColumn('venta_cabeceras', 'descuento')) {
            DB::table('facturas')
                ->join('venta_cabeceras', 'venta_cabeceras.id', '=', 'facturas.venta_id')
                ->update([
                    'facturas.descuento_global' => DB::raw('COALESCE(venta_cabeceras.descuento, 0)'),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropColumn('descuento_global');
        });
    }
};
