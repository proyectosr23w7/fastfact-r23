<?php

use App\Enums\TipoFacturacionEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $configuracion = DB::table('configuraciones')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();

        if (! $configuracion) {
            DB::table('configuraciones')->insert([
                'facturacion_habilitada' => false,
                'tipo_facturacion' => TipoFacturacionEnum::NO_EMITE->value,
                'ambiente_facturacion' => 'piloto',
                'token_siat' => null,
                'codigo_sistema' => null,
                'tipo_impresion' => 'ticket',
                'metodo_salida' => 'peps',
                'metodo_costos' => 'promedio_ponderado',
                'multiples_precios' => false,
                'precios_por_cantidad' => false,
                'pagos_credito_habilitados' => false,
                'confirmacion_rapida_ventas' => false,
                'estado' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return;
        }

        DB::table('configuraciones')
            ->where('id', '<>', $configuracion->id)
            ->delete();
    }

    public function down(): void
    {
        // No reversible: esta normalizacion consolida registros duplicados en uno solo.
    }
};
