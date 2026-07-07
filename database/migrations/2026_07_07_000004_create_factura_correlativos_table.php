<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('factura_correlativos')) {
            Schema::create('factura_correlativos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
                $table->foreignId('punto_venta_id')->constrained('puntos_venta')->cascadeOnDelete();
                $table->string('ambiente_facturacion', 20)->default('piloto');
                $table->unsignedTinyInteger('tipo_facturacion')->default(0);
                $table->unsignedBigInteger('ultimo_numero')->default(0);
                $table->timestamps();

                $table->unique(
                    ['sucursal_id', 'punto_venta_id', 'ambiente_facturacion', 'tipo_facturacion'],
                    'factura_correlativos_contexto_unique',
                );
            });
        }

        $this->seedFromExistingInvoices();
    }

    public function down(): void
    {
        Schema::dropIfExists('factura_correlativos');
    }

    private function seedFromExistingInvoices(): void
    {
        if (! Schema::hasTable('facturas') || ! Schema::hasTable('factura_correlativos')) {
            return;
        }

        DB::table('facturas')
            ->select([
                'sucursal_id',
                'punto_venta_id',
                DB::raw("COALESCE(ambiente_facturacion, 'piloto') as ambiente_facturacion"),
                DB::raw('COALESCE(tipo_facturacion, 0) as tipo_facturacion'),
                DB::raw('MAX(numero_factura) as ultimo_numero'),
            ])
            ->whereNull('cafc_id')
            ->whereNotNull('numero_factura')
            ->whereNotNull('sucursal_id')
            ->whereNotNull('punto_venta_id')
            ->groupBy('sucursal_id', 'punto_venta_id', 'ambiente_facturacion', 'tipo_facturacion')
            ->orderBy('sucursal_id')
            ->orderBy('punto_venta_id')
            ->get()
            ->each(function ($row): void {
                DB::table('factura_correlativos')->updateOrInsert(
                    [
                        'sucursal_id' => $row->sucursal_id,
                        'punto_venta_id' => $row->punto_venta_id,
                        'ambiente_facturacion' => $row->ambiente_facturacion,
                        'tipo_facturacion' => $row->tipo_facturacion,
                    ],
                    [
                        'ultimo_numero' => $row->ultimo_numero,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            });
    }
};
