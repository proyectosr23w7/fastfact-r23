<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {

            $table->foreignId('personal_id')
                ->nullable()
                ->after('id')
                ->constrained('personal')
                ->nullOnDelete();

            $table->foreignId('sucursal_id')
                ->nullable()
                ->after('personal_id')
                ->constrained('sucursales')
                ->nullOnDelete();

            $table->boolean('activo')->default(1)->after('password');
            $table->timestamp('ultimo_acceso')->nullable()->after('remember_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
