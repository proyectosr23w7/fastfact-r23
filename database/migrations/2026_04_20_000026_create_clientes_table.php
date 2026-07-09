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

        $now = now();

        $clientesEspeciales = [
            [
                'codigo' => 'SIAT-99001',
                'nombre' => 'Cliente especial SIAT 99001',
                'razon_social' => 'CLIENTE ESPECIAL SIAT 99001',
                'nit_ci' => '99001',
            ],
            [
                'codigo' => 'SIAT-99002',
                'nombre' => 'Control Tributario',
                'razon_social' => 'Control Tributario',
                'nit_ci' => '99002',
            ],
            [
                'codigo' => 'SIAT-99003',
                'nombre' => 'VENTAS MENORES DEL DÍA',
                'razon_social' => 'VENTAS MENORES DEL DÍA',
                'nit_ci' => '99003',
            ],
        ];

        foreach ($clientesEspeciales as $cliente) {
            DB::table('clientes')->updateOrInsert(
                ['codigo' => $cliente['codigo']],
                [
                    ...$cliente,
                    'estado' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
