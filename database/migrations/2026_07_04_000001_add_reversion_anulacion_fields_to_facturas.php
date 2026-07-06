<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->timestamp('anulacion_revertida_at')->nullable()->after('datos_respuesta_siat');
            $table->foreignId('anulacion_revertida_user_id')->nullable()->after('anulacion_revertida_at')->constrained('users')->nullOnDelete();
            $table->string('anulacion_reversion_codigo_respuesta', 50)->nullable()->after('anulacion_revertida_user_id');
            $table->text('anulacion_reversion_descripcion')->nullable()->after('anulacion_reversion_codigo_respuesta');
            $table->json('anulacion_reversion_respuesta_siat')->nullable()->after('anulacion_reversion_descripcion');
        });
    }

    public function down(): void
    {
        Schema::table('facturas', function (Blueprint $table) {
            $table->dropForeign(['anulacion_revertida_user_id']);
            $table->dropColumn([
                'anulacion_revertida_at',
                'anulacion_revertida_user_id',
                'anulacion_reversion_codigo_respuesta',
                'anulacion_reversion_descripcion',
                'anulacion_reversion_respuesta_siat',
            ]);
        });
    }
};
