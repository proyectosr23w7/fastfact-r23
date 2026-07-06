<?php

use App\Models\Configuracion\Configuracion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cuis') || Schema::hasColumn('cuis', 'ambiente_facturacion')) {
            return;
        }

        Schema::table('cuis', function (Blueprint $table) {
            $table->string('ambiente_facturacion', 20)->default('piloto')->after('punto_venta_id');
            $table->index(['sucursal_id', 'punto_venta_id', 'ambiente_facturacion', 'estado'], 'cuis_contexto_ambiente_estado_idx');
        });

        $ambiente = Configuracion::query()->value('ambiente_facturacion') ?: 'piloto';

        DB::table('cuis')->update(['ambiente_facturacion' => $ambiente]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('cuis') || ! Schema::hasColumn('cuis', 'ambiente_facturacion')) {
            return;
        }

        Schema::table('cuis', function (Blueprint $table) {
            $table->dropIndex('cuis_contexto_ambiente_estado_idx');
            $table->dropColumn('ambiente_facturacion');
        });
    }
};
