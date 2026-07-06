<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('preferencias_empresa', function (Blueprint $table) {
            $table->id();
            $table->tinyInteger('facturacion')->default(0); // 0=no,1=electronica,2=computarizada
            $table->tinyInteger('ambiente')->default(1); // 1=producción,2=piloto
            $table->string('tipo_impresion')->default('rollo'); // rollo, media carta
            $table->boolean('manejo_unidades')->default(0);
            $table->boolean('ventas_credito')->default(0);
            $table->string('metodo_salida')->default('ueps'); // ueps, peps
            $table->boolean('uso_lotes')->default(0);
            $table->boolean('fecha_vencimiento')->default(0);
            $table->string('metodo_costos')->default('lotes'); // lotes, promedio ponderado
            $table->boolean('devolucion_ventas')->default(0);
            $table->boolean('precios_independientes_sucursales')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('preferencias_empresa');
    }
};
