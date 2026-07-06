<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuraciones', function (Blueprint $table) {
            $table->id();
            $table->boolean('facturacion_habilitada')->default(false);
            $table->string('tipo_facturacion')->default('manual');
            $table->string('ambiente_facturacion')->default('piloto');
            $table->text('token_siat')->nullable();
            $table->string('codigo_sistema')->nullable();
            $table->string('tipo_impresion')->default('ticket');
            $table->string('metodo_salida')->default('impresion');
            $table->string('metodo_costos')->default('promedio');
            $table->boolean('multiples_precios')->default(false);
            $table->boolean('precios_por_cantidad')->default(false);
            $table->boolean('estado')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuraciones');
    }
};
