<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cufd')) {
            return;
        }

        Schema::table('cufd', function (Blueprint $table) {
            if (! Schema::hasColumn('cufd', 'ambiente_facturacion')) {
                $table->string('ambiente_facturacion', 20)->default('piloto')->after('punto_venta_id');
                $table->index(['sucursal_id', 'punto_venta_id', 'ambiente_facturacion', 'estado'], 'cufd_contexto_ambiente_estado_idx');
            }
        });

        $ambienteActual = DB::table('configuraciones')->value('ambiente_facturacion') ?: 'piloto';

        DB::table('cufd')
            ->whereNull('ambiente_facturacion')
            ->orWhere('ambiente_facturacion', '')
            ->update(['ambiente_facturacion' => $ambienteActual]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('cufd')) {
            return;
        }

        Schema::table('cufd', function (Blueprint $table) {
            if (Schema::hasColumn('cufd', 'ambiente_facturacion')) {
                $table->dropIndex('cufd_contexto_ambiente_estado_idx');
                $table->dropColumn('ambiente_facturacion');
            }
        });
    }
};
