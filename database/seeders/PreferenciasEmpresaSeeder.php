<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PreferenciasEmpresaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('preferencias_empresa')->insert([
            'facturacion' => 0,
            'ambiente' => 1,
            'tipo_impresion' => 'rollo',
            'manejo_unidades' => 0,
            'ventas_credito' => 0,
            'metodo_salida' => 'ueps',
            'uso_lotes' => 0,
            'fecha_vencimiento' => 0,
            'metodo_costos' => 'lotes',
            'devolucion_ventas' => 0,
            'precios_independientes_sucursales' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
