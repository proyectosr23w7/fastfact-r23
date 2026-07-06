<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('facturas')) {
            Schema::table('facturas', function (Blueprint $table) {
                if (! Schema::hasColumn('facturas', 'origen')) {
                    $table->string('origen', 40)->default('venta')->after('id');
                }

                if (! Schema::hasColumn('facturas', 'referencia_externa')) {
                    $table->string('referencia_externa', 120)->nullable()->after('origen');
                }

                if (! Schema::hasColumn('facturas', 'metadata')) {
                    $table->json('metadata')->nullable()->after('datos_respuesta_siat');
                }
            });

            $this->hacerVentaIdNullable();
        }

        if (! Schema::hasTable('factura_detalles')) {
            Schema::create('factura_detalles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('factura_id')->constrained('facturas')->cascadeOnDelete();
                $table->foreignId('articulo_id')->nullable()->constrained('articulos')->nullOnDelete();
                $table->string('actividad_economica', 20);
                $table->unsignedBigInteger('codigo_producto_sin');
                $table->string('codigo_producto', 80);
                $table->string('descripcion', 500);
                $table->decimal('cantidad', 18, 5);
                $table->unsignedInteger('unidad_medida');
                $table->decimal('precio_unitario', 18, 5);
                $table->decimal('monto_descuento', 18, 5)->default(0);
                $table->decimal('subtotal', 18, 5);
                $table->string('numero_serie', 100)->nullable();
                $table->string('numero_imei', 100)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['factura_id', 'codigo_producto']);
                $table->index('codigo_producto_sin');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('factura_detalles');

        if (Schema::hasTable('facturas')) {
            Schema::table('facturas', function (Blueprint $table) {
                foreach (['metadata', 'referencia_externa', 'origen'] as $column) {
                    if (Schema::hasColumn('facturas', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }

    private function hacerVentaIdNullable(): void
    {
        if (! Schema::hasColumn('facturas', 'venta_id')) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            $database = DB::getDatabaseName();
            $foreignKey = DB::table('information_schema.KEY_COLUMN_USAGE')
                ->where('TABLE_SCHEMA', $database)
                ->where('TABLE_NAME', 'facturas')
                ->where('COLUMN_NAME', 'venta_id')
                ->whereNotNull('REFERENCED_TABLE_NAME')
                ->value('CONSTRAINT_NAME');

            if ($foreignKey) {
                DB::statement("ALTER TABLE facturas DROP FOREIGN KEY `{$foreignKey}`");
            }

            DB::statement('ALTER TABLE facturas MODIFY venta_id BIGINT UNSIGNED NULL');

            $indexExists = DB::table('information_schema.STATISTICS')
                ->where('TABLE_SCHEMA', $database)
                ->where('TABLE_NAME', 'facturas')
                ->where('INDEX_NAME', 'facturas_venta_id_unique')
                ->exists();

            if (! $indexExists) {
                DB::statement('ALTER TABLE facturas ADD UNIQUE facturas_venta_id_unique (venta_id)');
            }

            DB::statement('ALTER TABLE facturas ADD CONSTRAINT facturas_venta_id_foreign FOREIGN KEY (venta_id) REFERENCES venta_cabeceras(id) ON DELETE SET NULL');

            return;
        }

        if ($driver === 'sqlite') {
            return;
        }
    }
};