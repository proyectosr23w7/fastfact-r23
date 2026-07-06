<?php

namespace Database\Seeders;

use App\Models\Marca;
use Illuminate\Database\Seeder;

class MarcaSeeder extends Seeder
{
    public function run(): void
    {
        $marcas = [
            ['nombre' => 'Coca-Cola', 'descripcion' => 'Bebidas gaseosas y refrescos.'],
            ['nombre' => 'Pil', 'descripcion' => 'Productos lacteos de consumo masivo.'],
            ['nombre' => 'La Francesa', 'descripcion' => 'Harinas, fideos y abarrotes.'],
            ['nombre' => 'Pacena', 'descripcion' => 'Bebidas tradicionales y gaseosas.'],
            ['nombre' => 'Soboce', 'descripcion' => 'Materiales e insumos industriales.'],
            ['nombre' => 'Alpina', 'descripcion' => 'Lacteos y derivados.'],
            ['nombre' => 'Monopol', 'descripcion' => 'Limpieza y cuidado del hogar.'],
            ['nombre' => 'Generica', 'descripcion' => 'Marca de apoyo para productos sin marca comercial definida.'],
        ];

        foreach ($marcas as $marca) {
            Marca::query()->updateOrCreate(
                ['nombre' => $marca['nombre']],
                [
                    'descripcion' => $marca['descripcion'],
                    'estado' => true,
                ],
            );
        }
    }
}
