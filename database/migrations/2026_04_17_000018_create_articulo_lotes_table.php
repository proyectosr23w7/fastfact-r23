<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('articulo_lotes')) {
            return;
        }

        Schema::create('articulo_lotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('articulo_id')->constrained('articulos');
            $table->foreignId('sucursal_id')->constrained('sucursales');
            $table->string('lote');
            $table->date('fecha_vencimiento')->nullable();
            $table->decimal('cantidad_inicial', 14, 2);
            $table->decimal('cantidad_actual', 14, 2);
            $table->decimal('costo_unitario', 14, 2);
            $table->boolean('estado')->default(true);
            $table->timestamps();

            $table->unique(['articulo_id', 'sucursal_id', 'lote']);
            $table->index(['articulo_id', 'sucursal_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articulo_lotes');
    }
};
