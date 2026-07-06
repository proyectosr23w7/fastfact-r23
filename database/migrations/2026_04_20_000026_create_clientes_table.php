<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('clientes')) {
            Schema::create('clientes', function (Blueprint $table) {
                $table->id();
                $table->string('codigo', 50)->unique();
                $table->string('nombre');
                $table->string('razon_social')->nullable();
                $table->string('nit_ci', 50)->nullable();
                $table->string('telefono', 50)->nullable();
                $table->string('correo', 150)->nullable();
                $table->text('direccion')->nullable();
                $table->boolean('estado')->default(true);
                $table->timestamps();
                $table->index(['nombre', 'estado']);
            });
        }

        if (! DB::table('clientes')->where('codigo', 'CLI-0001')->exists()) {
            DB::table('clientes')->insert([
                'codigo' => 'CLI-0001',
                'nombre' => 'Cliente Varios',
                'razon_social' => 'Consumidor Final',
                'nit_ci' => '0',
                'estado' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
