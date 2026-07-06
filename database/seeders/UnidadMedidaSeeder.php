<?php

namespace Database\Seeders;

use App\Models\UnidadMedida;
use Illuminate\Database\Seeder;

class UnidadMedidaSeeder extends Seeder
{
    public function run(): void
    {
        $unidades = [
            ['nombre' => 'Unidad', 'abreviatura' => 'u'],
            ['nombre' => 'Caja', 'abreviatura' => 'cj'],
            ['nombre' => 'Paquete', 'abreviatura' => 'paq'],
            ['nombre' => 'Bolsa', 'abreviatura' => 'bls'],
            ['nombre' => 'Kilogramo', 'abreviatura' => 'kg'],
            ['nombre' => 'Gramo', 'abreviatura' => 'g'],
            ['nombre' => 'Litro', 'abreviatura' => 'l'],
            ['nombre' => 'Mililitro', 'abreviatura' => 'ml'],
            ['nombre' => 'Metro', 'abreviatura' => 'm'],
            ['nombre' => 'Docena', 'abreviatura' => 'doc'],
        ];

        foreach ($unidades as $unidad) {
            UnidadMedida::query()->updateOrCreate(
                ['nombre' => $unidad['nombre']],
                [
                    'abreviatura' => $unidad['abreviatura'],
                    'estado' => true,
                ],
            );
        }
    }
}
