<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('configuraciones')) {
            return;
        }

        Schema::table('configuraciones', function (Blueprint $table): void {
            if (! Schema::hasColumn('configuraciones', 'mostrar_telefono_cliente_listado_facturas')) {
                $table->boolean('mostrar_telefono_cliente_listado_facturas')
                    ->default(false)
                    ->after('mostrar_descuento_detalle_factura');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('configuraciones')) {
            return;
        }

        Schema::table('configuraciones', function (Blueprint $table): void {
            if (Schema::hasColumn('configuraciones', 'mostrar_telefono_cliente_listado_facturas')) {
                $table->dropColumn('mostrar_telefono_cliente_listado_facturas');
            }
        });
    }
};
