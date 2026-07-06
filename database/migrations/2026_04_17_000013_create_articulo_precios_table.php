<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('articulo_precios')) {
            return;
        }

        Schema::create('articulo_precios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('articulo_id')->constrained('articulos')->cascadeOnDelete();
            $table->decimal('cantidad_minima', 14, 2);
            $table->decimal('precio', 14, 2);
            $table->string('tipo_precio', 50)->default('general');
            $table->boolean('estado')->default(true);
            $table->timestamps();

            $table->unique(['articulo_id', 'cantidad_minima', 'tipo_precio'], 'articulo_precios_unique_rule');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articulo_precios');
    }
};
