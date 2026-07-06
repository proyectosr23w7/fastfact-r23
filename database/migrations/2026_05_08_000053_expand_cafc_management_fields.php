<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cafc')) {
            return;
        }

        Schema::table('cafc', function (Blueprint $table) {
            if (! Schema::hasColumn('cafc', 'pin')) {
                $table->string('pin', 120)->nullable()->after('codigo');
            }

            if (! Schema::hasColumn('cafc', 'numero_inicial')) {
                $table->unsignedInteger('numero_inicial')->nullable()->after('fecha_fin_vigencia');
            }

            if (! Schema::hasColumn('cafc', 'numero_final')) {
                $table->unsignedInteger('numero_final')->nullable()->after('numero_inicial');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cafc')) {
            return;
        }

        Schema::table('cafc', function (Blueprint $table) {
            if (Schema::hasColumn('cafc', 'numero_final')) {
                $table->dropColumn('numero_final');
            }

            if (Schema::hasColumn('cafc', 'numero_inicial')) {
                $table->dropColumn('numero_inicial');
            }

            if (Schema::hasColumn('cafc', 'pin')) {
                $table->dropColumn('pin');
            }
        });
    }
};
