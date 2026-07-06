<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('articulo_stocks')) {
            Schema::create('articulo_stocks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('articulo_id')->constrained('articulos')->cascadeOnDelete();
                $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
                $table->decimal('stock_actual', 14, 2)->default(0);
                $table->timestamps();
                $table->unique(['articulo_id', 'sucursal_id']);
            });
        }

        $casaMatrizId = DB::table('sucursales')->where('codigo', 0)->value('id')
            ?? DB::table('sucursales')->orderBy('id')->value('id');

        if ($casaMatrizId) {
            DB::table('articulos')
                ->where('stock_actual', '>', 0)
                ->orderBy('id')
                ->chunkById(100, function ($articulos) use ($casaMatrizId): void {
                    foreach ($articulos as $articulo) {
                        DB::table('articulo_stocks')->updateOrInsert(
                            [
                                'articulo_id' => $articulo->id,
                                'sucursal_id' => $casaMatrizId,
                            ],
                            [
                                'stock_actual' => $articulo->stock_actual,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ],
                        );
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('articulo_stocks');
    }
};
