<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('venta_cabeceras')) {
            return;
        }

        Schema::table('venta_cabeceras', function (Blueprint $table) {
            if (! Schema::hasColumn('venta_cabeceras', 'iva')) {
                $table->decimal('iva', 14, 2)->default(0)->after('impuesto');
            }

            if (! Schema::hasColumn('venta_cabeceras', 'it')) {
                $table->decimal('it', 14, 2)->default(0)->after('iva');
            }
        });

        DB::table('venta_cabeceras')
            ->select(['id', 'total'])
            ->orderBy('id')
            ->chunkById(100, function ($ventas): void {
                foreach ($ventas as $venta) {
                    $base = round((float) ($venta->total ?? 0), 2);

                    DB::table('venta_cabeceras')
                        ->where('id', $venta->id)
                        ->update([
                            'iva' => round($base * 0.13, 2),
                            'it' => round($base * 0.03, 2),
                            'impuesto' => round($base * 0.13, 2),
                        ]);
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('venta_cabeceras')) {
            return;
        }

        Schema::table('venta_cabeceras', function (Blueprint $table) {
            if (Schema::hasColumn('venta_cabeceras', 'it')) {
                $table->dropColumn('it');
            }

            if (Schema::hasColumn('venta_cabeceras', 'iva')) {
                $table->dropColumn('iva');
            }
        });
    }
};
