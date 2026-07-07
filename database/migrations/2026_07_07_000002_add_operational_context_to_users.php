<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || Schema::hasColumn('users', 'punto_venta_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('punto_venta_id')
                ->nullable()
                ->after('sucursal_id')
                ->constrained('puntos_venta')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'punto_venta_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('punto_venta_id');
        });
    }
};
