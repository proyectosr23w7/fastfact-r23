<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EmpresaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('empresas')->insert([
            'nombre_empresa' => 'Empresa Demostraciom',
            'titular' => 'TechDev R23W7',
            'nit' => '123456789',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
