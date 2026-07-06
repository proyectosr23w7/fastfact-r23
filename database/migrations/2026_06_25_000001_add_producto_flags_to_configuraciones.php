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

        Schema::table('configuraciones', function (Blueprint $table) {
            if (! Schema::hasColumn('configuraciones', 'productos_categorias_habilitadas')) {
                $table->boolean('productos_categorias_habilitadas')->default(true)->after('precios_por_cantidad');
            }

            if (! Schema::hasColumn('configuraciones', 'productos_marcas_habilitadas')) {
                $table->boolean('productos_marcas_habilitadas')->default(false)->after('productos_categorias_habilitadas');
            }

            if (! Schema::hasColumn('configuraciones', 'productos_busqueda_avanzada_habilitada')) {
                $table->boolean('productos_busqueda_avanzada_habilitada')->default(false)->after('productos_marcas_habilitadas');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('configuraciones')) {
            return;
        }

        Schema::table('configuraciones', function (Blueprint $table) {
            foreach ([
                'productos_busqueda_avanzada_habilitada',
                'productos_marcas_habilitadas',
                'productos_categorias_habilitadas',
            ] as $column) {
                if (Schema::hasColumn('configuraciones', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};