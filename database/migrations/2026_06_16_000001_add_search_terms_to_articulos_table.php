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
            if (! Schema::hasColumn('articulos', 'tags')) {
                $table->json('tags')->nullable()->after('descripcion');
            }

            if (! Schema::hasColumn('articulos', 'alias')) {
                $table->json('alias')->nullable()->after('tags');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('articulos')) {
            return;
        }

        Schema::table('articulos', function (Blueprint $table) {
            foreach (['alias', 'tags'] as $column) {
                if (Schema::hasColumn('articulos', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
