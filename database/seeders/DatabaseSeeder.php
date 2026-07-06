<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            CategoriaSeeder::class,
            MarcaSeeder::class,
            UnidadMedidaSeeder::class,
        ]);

        if (app()->environment('production') && ! env('FASTFACT_SEED_ADMIN_EMAIL')) {
            return;
        }

        User::query()->updateOrCreate([
            'email' => env('FASTFACT_SEED_ADMIN_EMAIL', 'proyectosr23w7@gmail.com'),
        ], [
            'name' => env('FASTFACT_SEED_ADMIN_NAME', 'TechDevR23W7'),
            'password' => env('FASTFACT_SEED_ADMIN_PASSWORD', '12345678'),
            'email_verified_at' => now(),
        ]);
    }
}
