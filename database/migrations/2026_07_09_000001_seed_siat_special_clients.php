<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('clientes')) {
            return;
        }

        $this->seedSiatSpecialClients();
        $this->removeZeroDocumentClientsWithoutMovements();
    }

    public function down(): void
    {
        if (! Schema::hasTable('clientes')) {
            return;
        }

        foreach (['SIAT-99001', 'SIAT-99002', 'SIAT-99003'] as $codigo) {
            $cliente = DB::table('clientes')->where('codigo', $codigo)->first(['id']);

            if (! $cliente || $this->clientHasMovements((int) $cliente->id)) {
                continue;
            }

            DB::table('clientes')->where('id', $cliente->id)->delete();
        }
    }

    private function seedSiatSpecialClients(): void
    {
        $now = now();

        $clientes = [
            [
                'codigo' => 'SIAT-99001',
                'nombre' => 'Cliente especial SIAT 99001',
                'razon_social' => 'CLIENTE ESPECIAL SIAT 99001',
                'nit_ci' => '99001',
            ],
            [
                'codigo' => 'SIAT-99002',
                'nombre' => 'Control Tributario',
                'razon_social' => 'Control Tributario',
                'nit_ci' => '99002',
            ],
            [
                'codigo' => 'SIAT-99003',
                'nombre' => 'VENTAS MENORES DEL DÍA',
                'razon_social' => 'VENTAS MENORES DEL DÍA',
                'nit_ci' => '99003',
            ],
        ];

        foreach ($clientes as $cliente) {
            $existing = DB::table('clientes')
                ->where('codigo', $cliente['codigo'])
                ->orWhere('nit_ci', $cliente['nit_ci'])
                ->orderByRaw("CASE WHEN codigo = ? THEN 0 ELSE 1 END", [$cliente['codigo']])
                ->first(['id']);

            $payload = [
                ...$cliente,
                'tipo_documento_identidad' => '5',
                'complemento' => null,
                'telefono' => null,
                'correo' => null,
                'direccion' => null,
                'estado' => true,
                'updated_at' => $now,
            ];

            if ($existing) {
                DB::table('clientes')->where('id', $existing->id)->update($payload);

                continue;
            }

            DB::table('clientes')->insert([
                ...$payload,
                'created_at' => $now,
            ]);
        }
    }

    private function removeZeroDocumentClientsWithoutMovements(): void
    {
        DB::table('clientes')
            ->orderBy('id')
            ->get(['id', 'nit_ci'])
            ->filter(fn (object $cliente) => preg_match('/^0+$/', trim((string) $cliente->nit_ci)) === 1)
            ->each(function (object $cliente) {
                if ($this->clientHasMovements((int) $cliente->id)) {
                    DB::table('clientes')
                        ->where('id', $cliente->id)
                        ->update([
                            'estado' => false,
                            'updated_at' => now(),
                        ]);

                    return;
                }

                DB::table('clientes')->where('id', $cliente->id)->delete();
            });
    }

    private function clientHasMovements(int $clienteId): bool
    {
        if (Schema::hasTable('venta_cabeceras') && DB::table('venta_cabeceras')->where('cliente_id', $clienteId)->exists()) {
            return true;
        }

        if (Schema::hasTable('facturas') && DB::table('facturas')->where('cliente_id', $clienteId)->exists()) {
            return true;
        }

        return false;
    }
};
