<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('articulos')) {
            return;
        }

        Schema::create('articulos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_generico')->unique();
            $table->string('codigo_barras')->nullable()->unique();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->foreignId('categoria_id')->constrained('categorias');
            $table->foreignId('marca_id')->nullable()->constrained('marcas')->nullOnDelete();
            $table->foreignId('unidad_medida_id')->constrained('unidades_medida');
            $table->decimal('stock_minimo', 14, 2)->default(0);
            $table->decimal('stock_actual', 14, 2)->default(0);
            $table->decimal('costo', 14, 2)->default(0);
            $table->decimal('precio_base', 14, 2);
            $table->boolean('estado')->default(true);
            $table->timestamps();

            $table->index(['nombre', 'codigo_generico']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articulos');
    }
};
