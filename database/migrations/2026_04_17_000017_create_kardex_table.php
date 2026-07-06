<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('kardex')) {
            return;
        }

        Schema::create('kardex', function (Blueprint $table) {
            $table->id();
            $table->foreignId('articulo_id')->constrained('articulos');
            $table->foreignId('sucursal_id')->constrained('sucursales');
            $table->string('tipo_movimiento', 40);
            $table->string('referencia_tipo', 40);
            $table->unsignedBigInteger('referencia_id');
            $table->dateTime('fecha');
            $table->decimal('entrada', 14, 2)->default(0);
            $table->decimal('salida', 14, 2)->default(0);
            $table->decimal('saldo', 14, 2)->default(0);
            $table->decimal('costo_unitario', 14, 2)->default(0);
            $table->decimal('costo_total', 14, 2)->default(0);
            $table->decimal('stock_anterior', 14, 2)->default(0);
            $table->decimal('stock_nuevo', 14, 2)->default(0);
            $table->text('observacion')->nullable();
            $table->foreignId('user_id')->constrained('users');
            $table->timestamps();

            $table->index(['articulo_id', 'sucursal_id', 'fecha']);
            $table->index(['referencia_tipo', 'referencia_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kardex');
    }
};
