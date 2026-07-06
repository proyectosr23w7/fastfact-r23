<?php

namespace Database\Seeders;

use App\Models\Categoria;
use Illuminate\Database\Seeder;

class CategoriaSeeder extends Seeder
{
    public function run(): void
    {
        $categorias = [
            ['nombre' => 'Abarrotes', 'descripcion' => 'Productos de consumo masivo y canasta familiar.'],
            ['nombre' => 'Bebidas', 'descripcion' => 'Bebidas sin alcohol, energizantes y refrescos.'],
            ['nombre' => 'Limpieza', 'descripcion' => 'Productos para limpieza del hogar y negocio.'],
            ['nombre' => 'Cuidado Personal', 'descripcion' => 'Higiene personal y cuidado diario.'],
            ['nombre' => 'Lacteos', 'descripcion' => 'Leche, yogurt, queso y derivados.'],
            ['nombre' => 'Snacks', 'descripcion' => 'Galletas, frituras, dulces y aperitivos.'],
            ['nombre' => 'Papeleria', 'descripcion' => 'Material de oficina y utiles escolares.'],
            ['nombre' => 'Ferreteria', 'descripcion' => 'Insumos y herramientas de ferreteria ligera.'],
        ];

        foreach ($categorias as $categoria) {
            Categoria::query()->updateOrCreate(
                ['nombre' => $categoria['nombre']],
                [
                    'descripcion' => $categoria['descripcion'],
                    'estado' => true,
                ],
            );
        }
    }
}
