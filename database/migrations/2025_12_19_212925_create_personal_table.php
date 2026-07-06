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
        Schema::create('personal', function (Blueprint $table) {
            $table->id();

            $table->string('nombre_completo');
            $table->string('telefono')->nullable();
            $table->string('numero_documento')->nullable();

            $table->date('fecha_ingreso')->nullable();

            $table->foreignId('puesto_id')
                ->nullable()
                ->constrained('puestos')
                ->nullOnDelete();

            $table->boolean('activo')->default(1);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personal');
    }
};
