<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('articulos')) {
            return;
        }

        Schema::table('articulos', function (Blueprint $table) {
            if (! Schema::hasColumn('articulos', 'codigo_actividad_economica')) {
                $table->string('codigo_actividad_economica', 20)->nullable()->after('unidad_medida_id');
            }

            if (! Schema::hasColumn('articulos', 'codigo_producto_sin')) {
                $table->string('codigo_producto_sin', 30)->nullable()->after('codigo_actividad_economica');
            }

            if (! Schema::hasColumn('articulos', 'codigo_unidad_medida_siat')) {
                $table->string('codigo_unidad_medida_siat', 20)->nullable()->after('codigo_producto_sin');
            }
        });

        if (! Schema::hasTable('sin_productos_servicios') || ! Schema::hasTable('sin_unidades_medida')) {
            return;
        }

        $articulos = DB::table('articulos')
            ->leftJoin('unidades_medida', 'unidades_medida.id', '=', 'articulos.unidad_medida_id')
            ->select([
                'articulos.id',
                'articulos.nombre',
                'articulos.codigo_generico',
                'articulos.codigo_actividad_economica',
                'articulos.codigo_producto_sin',
                'articulos.codigo_unidad_medida_siat',
                'unidades_medida.nombre as unidad_nombre',
                'unidades_medida.abreviatura as unidad_abreviatura',
            ])
            ->orderBy('articulos.id')
            ->get();

        foreach ($articulos as $articulo) {
            $payload = [];

            if (! filled($articulo->codigo_producto_sin) || ! filled($articulo->codigo_actividad_economica)) {
                $producto = DB::table('sin_productos_servicios')
                    ->where('estado', true)
                    ->where(function ($query) use ($articulo) {
                        $query->where('descripcion', 'like', '%'.$articulo->nombre.'%');

                        if (filled($articulo->codigo_generico)) {
                            $query->orWhere('codigo_producto', (string) $articulo->codigo_generico);
                        }
                    })
                    ->orderBy('codigo_producto')
                    ->first();

                if ($producto) {
                    $payload['codigo_actividad_economica'] = (string) $producto->codigo_actividad;
                    $payload['codigo_producto_sin'] = (string) $producto->codigo_producto;
                }
            }

            if (! filled($articulo->codigo_unidad_medida_siat)) {
                $unidad = DB::table('sin_unidades_medida')
                    ->where('estado', true)
                    ->where(function ($query) use ($articulo) {
                        if (filled($articulo->unidad_nombre)) {
                            $query->where('descripcion', 'like', '%'.$articulo->unidad_nombre.'%');
                        }

                        if (filled($articulo->unidad_abreviatura)) {
                            $query->orWhere('descripcion', 'like', '%'.$articulo->unidad_abreviatura.'%');
                        }
                    })
                    ->orderBy('codigo_clasificador')
                    ->first();

                if ($unidad) {
                    $payload['codigo_unidad_medida_siat'] = (string) $unidad->codigo_clasificador;
                }
            }

            if ($payload !== []) {
                DB::table('articulos')
                    ->where('id', $articulo->id)
                    ->update($payload);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('articulos')) {
            return;
        }

        Schema::table('articulos', function (Blueprint $table) {
            foreach (['codigo_unidad_medida_siat', 'codigo_producto_sin', 'codigo_actividad_economica'] as $column) {
                if (Schema::hasColumn('articulos', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
