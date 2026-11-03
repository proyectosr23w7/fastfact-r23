<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('configuraciones') && ! Schema::hasColumn('configuraciones', 'textos_mayusculas_habilitados')) {
            Schema::table('configuraciones', function (Blueprint $table): void {
                $table->boolean('textos_mayusculas_habilitados')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('configuraciones') && Schema::hasColumn('configuraciones', 'textos_mayusculas_habilitados')) {
            Schema::table('configuraciones', function (Blueprint $table): void {
                $table->dropColumn('textos_mayusculas_habilitados');
            });
        }
    }
};
