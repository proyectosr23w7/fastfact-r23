<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eventos_significativos', function (Blueprint $table) {
            if (! Schema::hasColumn('eventos_significativos', 'cufd_recuperacion_id')) {
                $table->foreignId('cufd_recuperacion_id')
                    ->nullable()
                    ->after('cufd_evento_id')
                    ->constrained('cufd')
                    ->nullOnDelete();
            }
        });

        if (! Schema::hasTable('evento_significativo_paquetes')) {
            Schema::create('evento_significativo_paquetes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('evento_significativo_id')->constrained('eventos_significativos')->cascadeOnDelete();
                $table->foreignId('cufd_envio_id')->nullable()->constrained('cufd')->nullOnDelete();
                $table->unsignedInteger('numero_paquete')->default(1);
                $table->unsignedInteger('cantidad_facturas')->default(0);
                $table->string('codigo_recepcion', 255)->nullable();
                $table->string('codigo_estado', 40)->nullable();
                $table->text('descripcion_estado')->nullable();
                $table->string('estado', 40)->default('preparado');
                $table->string('hash_archivo', 255)->nullable();
                $table->string('nombre_archivo', 255)->nullable();
                $table->timestamp('fecha_envio')->nullable();
                $table->timestamp('fecha_validacion')->nullable();
                $table->json('datos_respuesta_recepcion')->nullable();
                $table->json('datos_respuesta_validacion')->nullable();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->timestamps();
            });
        }

        Schema::table('facturas', function (Blueprint $table) {
            if (! Schema::hasColumn('facturas', 'evento_significativo_paquete_id')) {
                $table->foreignId('evento_significativo_paquete_id')
                    ->nullable()
                    ->after('evento_significativo_id')
                    ->constrained('evento_significativo_paquetes')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            if (Schema::hasColumn('facturas', 'evento_significativo_paquete_id')) {
                $table->dropConstrainedForeignId('evento_significativo_paquete_id');
            }
        });

        Schema::dropIfExists('evento_significativo_paquetes');

        Schema::table('eventos_significativos', function (Blueprint $table) {
            if (Schema::hasColumn('eventos_significativos', 'cufd_recuperacion_id')) {
                $table->dropConstrainedForeignId('cufd_recuperacion_id');
            }
        });
    }
};
