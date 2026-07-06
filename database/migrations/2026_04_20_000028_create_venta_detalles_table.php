<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('venta_detalles')) {
            return;
        }

        Schema::create('venta_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('venta_cabeceras')->cascadeOnDelete();
            $table->foreignId('articulo_id')->constrained('articulos');
            $table->decimal('cantidad', 14, 2);
            $table->decimal('precio_unitario', 14, 2);
            $table->decimal('descuento', 14, 2)->default(0);
            $table->decimal('impuesto', 14, 2)->default(0);
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('costo_unitario', 14, 2)->default(0);
            $table->decimal('costo_total', 14, 2)->default(0);
            $table->decimal('utilidad_bruta', 14, 2)->default(0);
            $table->json('lote_consumido')->nullable();
            $table->timestamps();
            $table->index(['venta_id', 'articulo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venta_detalles');
    }
};
