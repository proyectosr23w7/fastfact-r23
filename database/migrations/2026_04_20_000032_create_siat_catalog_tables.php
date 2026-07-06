<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sin_actividades')) {
            Schema::create('sin_actividades', function (Blueprint $table) {
                $table->id();
                $table->string('codigo_clasificador', 60)->unique();
                $table->string('descripcion');
                $table->boolean('estado')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('sin_productos_servicios')) {
            Schema::create('sin_productos_servicios', function (Blueprint $table) {
                $table->id();
                $table->string('codigo_actividad', 60)->index();
                $table->string('codigo_producto', 60)->unique();
                $table->string('descripcion');
                $table->boolean('estado')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('sin_motivos_anulacion')) {
            Schema::create('sin_motivos_anulacion', function (Blueprint $table) {
                $table->id();
                $table->string('codigo_clasificador', 60)->unique();
                $table->string('descripcion');
                $table->boolean('estado')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('sin_leyendas')) {
            Schema::create('sin_leyendas', function (Blueprint $table) {
                $table->id();
                $table->string('codigo_actividad', 60)->nullable();
                $table->text('descripcion_leyenda');
                $table->boolean('estado')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('sin_documentos_identidad')) {
            Schema::create('sin_documentos_identidad', function (Blueprint $table) {
                $table->id();
                $table->string('codigo_clasificador', 60)->unique();
                $table->string('descripcion');
                $table->boolean('estado')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('sin_unidades_medida')) {
            Schema::create('sin_unidades_medida', function (Blueprint $table) {
                $table->id();
                $table->string('codigo_clasificador', 60)->unique();
                $table->string('descripcion');
                $table->boolean('estado')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('sin_monedas')) {
            Schema::create('sin_monedas', function (Blueprint $table) {
                $table->id();
                $table->string('codigo_clasificador', 60)->unique();
                $table->string('descripcion');
                $table->boolean('estado')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('sin_metodos_pago')) {
            Schema::create('sin_metodos_pago', function (Blueprint $table) {
                $table->id();
                $table->string('codigo_clasificador', 60)->unique();
                $table->string('descripcion');
                $table->boolean('estado')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sin_metodos_pago');
        Schema::dropIfExists('sin_monedas');
        Schema::dropIfExists('sin_unidades_medida');
        Schema::dropIfExists('sin_documentos_identidad');
        Schema::dropIfExists('sin_leyendas');
        Schema::dropIfExists('sin_motivos_anulacion');
        Schema::dropIfExists('sin_productos_servicios');
        Schema::dropIfExists('sin_actividades');
    }
};
