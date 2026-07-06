<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sin_metodos_pago')) {
            return;
        }

        Schema::table('sin_metodos_pago', function (Blueprint $table) {
            if (! Schema::hasColumn('sin_metodos_pago', 'habilitado_venta')) {
                $table->boolean('habilitado_venta')->default(true)->after('estado');
            }

            if (! Schema::hasColumn('sin_metodos_pago', 'es_predeterminado')) {
                $table->boolean('es_predeterminado')->default(false)->after('habilitado_venta');
            }

            if (! Schema::hasColumn('sin_metodos_pago', 'orden_operativo')) {
                $table->unsignedInteger('orden_operativo')->default(0)->after('es_predeterminado');
            }
        });

        $metodos = DB::table('sin_metodos_pago')
            ->orderBy('codigo_clasificador')
            ->get(['id', 'codigo_clasificador', 'descripcion', 'estado']);

        foreach ($metodos as $index => $metodo) {
            DB::table('sin_metodos_pago')
                ->where('id', $metodo->id)
                ->update([
                    'habilitado_venta' => (bool) $metodo->estado,
                    'orden_operativo' => $index + 1,
                ]);
        }

        $defaultId = DB::table('sin_metodos_pago')
            ->where('estado', true)
            ->where('habilitado_venta', true)
            ->where(function ($query) {
                $query->where('codigo_clasificador', '1')
                    ->orWhereRaw('LOWER(descripcion) like ?', ['%efectivo%']);
            })
            ->orderBy('orden_operativo')
            ->value('id');

        if (! $defaultId) {
            $defaultId = DB::table('sin_metodos_pago')
                ->where('estado', true)
                ->where('habilitado_venta', true)
                ->orderBy('orden_operativo')
                ->value('id');
        }

        if ($defaultId) {
            DB::table('sin_metodos_pago')->update(['es_predeterminado' => false]);
            DB::table('sin_metodos_pago')->where('id', $defaultId)->update(['es_predeterminado' => true]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('sin_metodos_pago')) {
            return;
        }

        Schema::table('sin_metodos_pago', function (Blueprint $table) {
            foreach (['orden_operativo', 'es_predeterminado', 'habilitado_venta'] as $column) {
                if (Schema::hasColumn('sin_metodos_pago', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
