<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('configuraciones')) {
            return;
        }

        Schema::table('configuraciones', function (Blueprint $table) {
            if (! Schema::hasColumn('configuraciones', 'token_siat_piloto')) {
                $table->longText('token_siat_piloto')->nullable()->after('codigo_sistema');
            }

            if (! Schema::hasColumn('configuraciones', 'token_siat_piloto_vigencia')) {
                $table->dateTime('token_siat_piloto_vigencia')->nullable()->after('token_siat_piloto');
            }

            if (! Schema::hasColumn('configuraciones', 'token_siat_produccion')) {
                $table->longText('token_siat_produccion')->nullable()->after('token_siat_piloto_vigencia');
            }

            if (! Schema::hasColumn('configuraciones', 'token_siat_produccion_vigencia')) {
                $table->dateTime('token_siat_produccion_vigencia')->nullable()->after('token_siat_produccion');
            }
        });

        if (Schema::hasColumn('configuraciones', 'token_siat')) {
            DB::table('configuraciones')
                ->whereNotNull('token_siat')
                ->whereNull('token_siat_piloto')
                ->update(['token_siat_piloto' => DB::raw('token_siat')]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('configuraciones')) {
            return;
        }

        Schema::table('configuraciones', function (Blueprint $table) {
            foreach ([
                'token_siat_piloto',
                'token_siat_piloto_vigencia',
                'token_siat_produccion',
                'token_siat_produccion_vigencia',
            ] as $column) {
                if (Schema::hasColumn('configuraciones', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
