<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->updateOrInsert(
            ['email' => 'proyectosr23w7@gmail.com'],
            [
                'name' => 'TechDevR23W7',
                'password' => Hash::make('12345678'),
                'email_verified_at' => now(),
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('users')
            ->where('email', 'proyectosr23w7@gmail.com')
            ->delete();
    }
};
