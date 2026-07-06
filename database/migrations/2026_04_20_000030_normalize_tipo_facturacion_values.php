<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('configuraciones')
            ->where('tipo_facturacion', 'manual')
            ->update(['tipo_facturacion' => '0']);

        DB::table('configuraciones')
            ->where('tipo_facturacion', 'electronica')
            ->update(['tipo_facturacion' => '1']);

        DB::table('configuraciones')
            ->where('tipo_facturacion', 'computarizada')
            ->update(['tipo_facturacion' => '2']);
    }

    public function down(): void
    {
        DB::table('configuraciones')
            ->where('tipo_facturacion', '0')
            ->update(['tipo_facturacion' => 'manual']);

        DB::table('configuraciones')
            ->where('tipo_facturacion', '1')
            ->update(['tipo_facturacion' => 'electronica']);

        DB::table('configuraciones')
            ->where('tipo_facturacion', '2')
            ->update(['tipo_facturacion' => 'computarizada']);
    }
};
