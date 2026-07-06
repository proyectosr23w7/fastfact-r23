<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cafc')) {
            Schema::create('cafc', function (Blueprint $table) {
                $table->id();
                $table->string('codigo', 120);
                $table->string('descripcion', 255)->nullable();
                $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
                $table->foreignId('punto_venta_id')->constrained('puntos_venta')->cascadeOnDelete();
                $table->string('ambiente_facturacion', 20)->default('piloto');
                $table->dateTime('fecha_inicio_vigencia')->nullable();
                $table->dateTime('fecha_fin_vigencia')->nullable();
                $table->boolean('estado')->default(true);
                $table->text('observacion')->nullable();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->timestamps();

                $table->unique(['codigo', 'ambiente_facturacion'], 'cafc_codigo_ambiente_unique');
            });
        }

        if (Schema::hasTable('eventos_significativos') && ! Schema::hasColumn('eventos_significativos', 'cafc_id')) {
            Schema::table('eventos_significativos', function (Blueprint $table) {
                $table->foreignId('cafc_id')
                    ->nullable()
                    ->after('cufd_recuperacion_id')
                    ->constrained('cafc')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('facturas') && ! Schema::hasColumn('facturas', 'cafc_id')) {
            Schema::table('facturas', function (Blueprint $table) {
                $table->foreignId('cafc_id')
                    ->nullable()
                    ->after('evento_significativo_paquete_id')
                    ->constrained('cafc')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('facturas') && Schema::hasColumn('facturas', 'cafc_id')) {
            Schema::table('facturas', function (Blueprint $table) {
                $table->dropConstrainedForeignId('cafc_id');
            });
        }

        if (Schema::hasTable('eventos_significativos') && Schema::hasColumn('eventos_significativos', 'cafc_id')) {
            Schema::table('eventos_significativos', function (Blueprint $table) {
                $table->dropConstrainedForeignId('cafc_id');
            });
        }

        Schema::dropIfExists('cafc');
    }
};
