<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('venta_detalle_lotes')) {
            return;
        }

        Schema::create('venta_detalle_lotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_detalle_id')->constrained('venta_detalles')->cascadeOnDelete();
            $table->foreignId('articulo_id')->constrained('articulos');
            $table->foreignId('articulo_lote_id')->nullable()->constrained('articulo_lotes')->nullOnDelete();
            $table->string('lote');
            $table->decimal('cantidad', 14, 2);
            $table->decimal('costo_unitario', 14, 2);
            $table->decimal('costo_total', 14, 2);
            $table->date('fecha_vencimiento')->nullable();
            $table->timestamps();
            $table->index(['venta_detalle_id', 'articulo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venta_detalle_lotes');
    }
};
