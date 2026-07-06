<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('puntos_venta', function (Blueprint $table) {
            $table->string('tipo_impresion')->default('ticket')->after('descripcion');
        });

        Schema::table('configuraciones', function (Blueprint $table) {
            $table->string('firma_digital_path')->nullable()->after('token_siat_produccion_vigencia');
            $table->string('firma_digital_nombre')->nullable()->after('firma_digital_path');
            $table->text('firma_digital_password')->nullable()->after('firma_digital_nombre');
        });

        $configuracion = DB::table('configuraciones')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();

        $tipoImpresion = match ($configuracion?->tipo_impresion ?? 'ticket') {
            'carta' => 'media_carta',
            'media_carta' => 'media_carta',
            default => 'ticket',
        };

        DB::table('puntos_venta')->update([
            'tipo_impresion' => $tipoImpresion,
        ]);
    }

    public function down(): void
    {
        Schema::table('configuraciones', function (Blueprint $table) {
            $table->dropColumn([
                'firma_digital_path',
                'firma_digital_nombre',
                'firma_digital_password',
            ]);
        });

        Schema::table('puntos_venta', function (Blueprint $table) {
            $table->dropColumn('tipo_impresion');
        });
    }
};
