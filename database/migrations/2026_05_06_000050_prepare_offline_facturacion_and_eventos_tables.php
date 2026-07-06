<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('eventos_significativos')) {
            Schema::table('eventos_significativos', function (Blueprint $table) {
                if (! Schema::hasColumn('eventos_significativos', 'ambiente_facturacion')) {
                    $table->string('ambiente_facturacion', 20)->default('piloto')->after('punto_venta_id');
                }

                if (! Schema::hasColumn('eventos_significativos', 'tipo_contingencia')) {
                    $table->string('tipo_contingencia', 30)->default('fuera_linea')->after('ambiente_facturacion');
                }

                if (! Schema::hasColumn('eventos_significativos', 'modo_activacion')) {
                    $table->string('modo_activacion', 20)->default('manual')->after('tipo_contingencia');
                }

                if (! Schema::hasColumn('eventos_significativos', 'registrado_siat_at')) {
                    $table->timestamp('registrado_siat_at')->nullable()->after('codigo_recepcion');
                }

                if (! Schema::hasColumn('eventos_significativos', 'observacion_interna')) {
                    $table->text('observacion_interna')->nullable()->after('datos_respuesta_siat');
                }
            });

            if (DB::connection()->getDriverName() !== 'sqlite') {
                DB::statement("ALTER TABLE eventos_significativos MODIFY fecha_fin DATETIME NULL");
            }
        }

        if (! Schema::hasTable('evento_significativo_reportes')) {
            Schema::create('evento_significativo_reportes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('evento_significativo_id')->nullable()->constrained('eventos_significativos')->nullOnDelete();
                $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
                $table->foreignId('punto_venta_id')->constrained('puntos_venta')->cascadeOnDelete();
                $table->string('ambiente_facturacion', 20)->default('piloto');
                $table->string('tipo_falla', 40);
                $table->text('descripcion')->nullable();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('facturas')) {
            Schema::table('facturas', function (Blueprint $table) {
                if (! Schema::hasColumn('facturas', 'evento_significativo_id')) {
                    $table->foreignId('evento_significativo_id')->nullable()->after('cufd_id')->constrained('eventos_significativos')->nullOnDelete();
                }

                if (! Schema::hasColumn('facturas', 'codigo_emision')) {
                    $table->unsignedTinyInteger('codigo_emision')->default(1)->after('ambiente_facturacion');
                }

                if (! Schema::hasColumn('facturas', 'estado_sincronizacion')) {
                    $table->string('estado_sincronizacion', 30)->default('no_aplica')->after('estado_factura');
                }

                if (! Schema::hasColumn('facturas', 'xml_fiscal')) {
                    $table->longText('xml_fiscal')->nullable()->after('hash_xml');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('facturas')) {
            Schema::table('facturas', function (Blueprint $table) {
                if (Schema::hasColumn('facturas', 'evento_significativo_id')) {
                    $table->dropConstrainedForeignId('evento_significativo_id');
                }

                if (Schema::hasColumn('facturas', 'codigo_emision')) {
                    $table->dropColumn('codigo_emision');
                }

                if (Schema::hasColumn('facturas', 'estado_sincronizacion')) {
                    $table->dropColumn('estado_sincronizacion');
                }

                if (Schema::hasColumn('facturas', 'xml_fiscal')) {
                    $table->dropColumn('xml_fiscal');
                }
            });
        }

        Schema::dropIfExists('evento_significativo_reportes');

        if (Schema::hasTable('eventos_significativos')) {
            Schema::table('eventos_significativos', function (Blueprint $table) {
                if (Schema::hasColumn('eventos_significativos', 'ambiente_facturacion')) {
                    $table->dropColumn('ambiente_facturacion');
                }

                if (Schema::hasColumn('eventos_significativos', 'tipo_contingencia')) {
                    $table->dropColumn('tipo_contingencia');
                }

                if (Schema::hasColumn('eventos_significativos', 'modo_activacion')) {
                    $table->dropColumn('modo_activacion');
                }

                if (Schema::hasColumn('eventos_significativos', 'registrado_siat_at')) {
                    $table->dropColumn('registrado_siat_at');
                }

                if (Schema::hasColumn('eventos_significativos', 'observacion_interna')) {
                    $table->dropColumn('observacion_interna');
                }
            });

            if (DB::connection()->getDriverName() !== 'sqlite') {
                DB::statement("ALTER TABLE eventos_significativos MODIFY fecha_fin DATETIME NOT NULL");
            }
        }
    }
};
