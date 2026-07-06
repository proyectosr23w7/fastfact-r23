<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('articulos')) {
            return;
        }

        Schema::table('articulos', function (Blueprint $table) {
            if (! Schema::hasColumn('articulos', 'atributos')) {
                $table->json('atributos')->nullable()->after('alias');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('articulos')) {
            return;
        }

        Schema::table('articulos', function (Blueprint $table) {
            if (Schema::hasColumn('articulos', 'atributos')) {
                $table->dropColumn('atributos');
            }
        });
    }
};
