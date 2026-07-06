<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('siat_sincronizaciones')) {
            Schema::create('siat_sincronizaciones', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('codigo_sucursal')->default(0);
                $table->unsignedInteger('codigo_punto_venta')->default(0);
                $table->string('tipo_catalogo', 60);
                $table->timestamp('fecha_sincronizacion');
                $table->string('estado', 30)->default('pendiente');
                $table->text('observacion')->nullable();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('cuis')) {
            Schema::create('cuis', function (Blueprint $table) {
                $table->id();
                $table->string('codigo', 120);
                $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
                $table->foreignId('punto_venta_id')->constrained('puntos_venta')->cascadeOnDelete();
                $table->timestamp('fecha_vigencia')->nullable();
                $table->boolean('estado')->default(true);
                $table->string('codigo_respuesta', 40)->nullable();
                $table->string('descripcion_respuesta')->nullable();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('cufd')) {
            Schema::create('cufd', function (Blueprint $table) {
                $table->id();
                $table->string('codigo', 255);
                $table->string('codigo_control', 255)->nullable();
                $table->string('direccion')->nullable();
                $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
                $table->foreignId('punto_venta_id')->constrained('puntos_venta')->cascadeOnDelete();
                $table->timestamp('fecha_vigencia')->nullable();
                $table->boolean('estado')->default(true);
                $table->string('codigo_respuesta', 40)->nullable();
                $table->string('descripcion_respuesta')->nullable();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('facturas')) {
            Schema::create('facturas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('venta_id')->unique()->constrained('venta_cabeceras')->cascadeOnDelete();
                $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
                $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
                $table->foreignId('punto_venta_id')->constrained('puntos_venta')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('cuis_id')->nullable()->constrained('cuis')->nullOnDelete();
                $table->foreignId('cufd_id')->nullable()->constrained('cufd')->nullOnDelete();
                $table->unsignedBigInteger('numero_factura')->nullable();
                $table->string('cuf', 255)->nullable();
                $table->string('codigo_recepcion', 255)->nullable();
                $table->string('codigo_metodo_pago', 60)->nullable();
                $table->string('codigo_documento_identidad', 60)->nullable();
                $table->unsignedTinyInteger('tipo_facturacion')->default(0);
                $table->string('ambiente_facturacion', 20)->default('piloto');
                $table->string('xml_generado_path')->nullable();
                $table->string('xml_firmado_path')->nullable();
                $table->string('pdf_path')->nullable();
                $table->string('hash_xml', 255)->nullable();
                $table->timestamp('fecha_emision')->nullable();
                $table->decimal('monto_total', 14, 2)->default(0);
                $table->decimal('monto_sujeto_iva', 14, 2)->default(0);
                $table->string('codigo_estado', 40)->nullable();
                $table->string('descripcion_estado')->nullable();
                $table->string('estado_factura', 30)->default('pendiente');
                $table->string('codigo_excepcion', 60)->nullable();
                $table->text('observacion')->nullable();
                $table->json('datos_respuesta_siat')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('factura_anulaciones')) {
            Schema::create('factura_anulaciones', function (Blueprint $table) {
                $table->id();
                $table->foreignId('factura_id')->constrained('facturas')->cascadeOnDelete();
                $table->string('codigo_motivo_anulacion', 40);
                $table->string('descripcion_motivo')->nullable();
                $table->timestamp('fecha_anulacion');
                $table->string('codigo_respuesta', 40)->nullable();
                $table->string('descripcion_respuesta')->nullable();
                $table->json('datos_respuesta_siat')->nullable();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('eventos_significativos')) {
            Schema::create('eventos_significativos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
                $table->foreignId('punto_venta_id')->constrained('puntos_venta')->cascadeOnDelete();
                $table->foreignId('cufd_evento_id')->nullable()->constrained('cufd')->nullOnDelete();
                $table->string('codigo_evento', 40);
                $table->string('descripcion');
                $table->dateTime('fecha_inicio');
                $table->dateTime('fecha_fin');
                $table->string('estado', 30)->default('pendiente');
                $table->string('codigo_recepcion', 255)->nullable();
                $table->json('datos_respuesta_siat')->nullable();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos_significativos');
        Schema::dropIfExists('factura_anulaciones');
        Schema::dropIfExists('facturas');
        Schema::dropIfExists('cufd');
        Schema::dropIfExists('cuis');
        Schema::dropIfExists('siat_sincronizaciones');
    }
};
