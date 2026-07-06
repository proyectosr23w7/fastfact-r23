<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            if (! Schema::hasColumn('clientes', 'tipo_documento_identidad')) {
                $table->string('tipo_documento_identidad', 10)->nullable()->after('nit_ci');
            }

            if (! Schema::hasColumn('clientes', 'complemento')) {
                $table->string('complemento', 20)->nullable()->after('tipo_documento_identidad');
            }
        });

        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::table('clientes')
                ->whereNull('tipo_documento_identidad')
                ->orderBy('id')
                ->lazyById()
                ->each(function (object $cliente) {
                    DB::table('clientes')
                        ->where('id', $cliente->id)
                        ->update([
                            'tipo_documento_identidad' => preg_match('/^[0-9]+$/', (string) $cliente->nit_ci) ? '5' : '1',
                        ]);
                });
        } else {
            DB::table('clientes')
                ->whereNull('tipo_documento_identidad')
                ->update([
                    'tipo_documento_identidad' => DB::raw("CASE WHEN nit_ci REGEXP '^[0-9]+$' THEN '5' ELSE '1' END"),
                ]);
        }

        DB::table('clientes')
            ->where(function ($query) {
                $query->whereNull('nombre')->orWhere('nombre', '');
            })
            ->update([
                'nombre' => DB::raw('COALESCE(razon_social, nit_ci, codigo)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            if (Schema::hasColumn('clientes', 'complemento')) {
                $table->dropColumn('complemento');
            }

            if (Schema::hasColumn('clientes', 'tipo_documento_identidad')) {
                $table->dropColumn('tipo_documento_identidad');
            }
        });
    }
};
