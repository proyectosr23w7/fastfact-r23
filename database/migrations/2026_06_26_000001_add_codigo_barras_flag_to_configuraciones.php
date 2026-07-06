<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuraciones', function (Blueprint $table): void {
            if (! Schema::hasColumn('configuraciones', 'productos_codigo_barras_habilitado')) {
                $table->boolean('productos_codigo_barras_habilitado')
                    ->default(true)
                    ->after('productos_busqueda_avanzada_habilitada');
            }
        });
    }

    public function down(): void
    {
        Schema::table('configuraciones', function (Blueprint $table): void {
            if (Schema::hasColumn('configuraciones', 'productos_codigo_barras_habilitado')) {
                $table->dropColumn('productos_codigo_barras_habilitado');
            }
        });
    }
};