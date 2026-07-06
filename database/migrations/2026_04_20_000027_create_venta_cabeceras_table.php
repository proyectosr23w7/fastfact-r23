<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('venta_cabeceras')) {
            return;
        }

        Schema::create('venta_cabeceras', function (Blueprint $table) {
            $table->id();
            $table->string('numero_venta')->unique();
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->foreignId('sucursal_id')->constrained('sucursales');
            $table->foreignId('punto_venta_id')->constrained('puntos_venta');
            $table->foreignId('user_id')->constrained('users');
            $table->date('fecha_venta');
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('descuento', 14, 2)->default(0);
            $table->decimal('impuesto', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('total_costo', 14, 2)->default(0);
            $table->decimal('utilidad_bruta', 14, 2)->default(0);
            $table->text('observacion')->nullable();
            $table->string('tipo_documento_venta', 40)->default('nota_venta');
            $table->boolean('requiere_factura')->default(false);
            $table->string('estado', 30)->default('borrador');
            $table->string('cuf', 120)->nullable();
            $table->string('cufd', 120)->nullable();
            $table->string('codigo_recepcion', 120)->nullable();
            $table->string('numero_factura', 50)->nullable();
            $table->string('codigo_excepcion', 50)->nullable();
            $table->string('estado_facturacion', 50)->nullable();
            $table->timestamps();
            $table->index(['fecha_venta', 'estado']);
            $table->index(['cliente_id', 'sucursal_id', 'punto_venta_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venta_cabeceras');
    }
};
