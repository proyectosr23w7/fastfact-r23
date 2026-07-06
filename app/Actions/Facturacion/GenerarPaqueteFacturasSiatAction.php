<?php

namespace App\Actions\Facturacion;

use App\Models\Factura;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Phar;
use PharData;

class GenerarPaqueteFacturasSiatAction
{
    /**
     * @param  Collection<int, Factura>  $facturas
     * @return array{binary: string, hash: string, cantidad: int, nombre_archivo: string}
     */
    public function __invoke(Collection $facturas, int $eventoId, int $numeroPaquete): array
    {
        if ($facturas->isEmpty()) {
            abort(422, 'No existen facturas para construir el paquete SIAT.');
        }

        if (! class_exists(PharData::class)) {
            abort(422, 'La extension Phar de PHP no esta disponible para construir paquetes TAR.GZ.');
        }

        $basePath = storage_path('app/siat/tmp/eventos');
        $packageKey = "evento-{$eventoId}-paquete-{$numeroPaquete}-".Str::lower(Str::random(10));
        $workingDirectory = "{$basePath}/{$packageKey}";
        $xmlDirectory = "{$workingDirectory}/xml";
        $tarPath = "{$workingDirectory}/{$packageKey}.tar";
        $tarGzPath = "{$tarPath}.gz";

        File::ensureDirectoryExists($xmlDirectory);

        try {
            foreach ($facturas as $factura) {
                $xml = trim((string) $factura->xml_fiscal);

                if ($xml === '') {
                    abort(422, "La factura {$factura->numero_factura} no tiene XML fiscal almacenado para el envio por paquetes.");
                }

                $filename = ($factura->cuf ?: "factura-{$factura->id}").'.xml';
                File::put("{$xmlDirectory}/{$filename}", $xml);
            }

            if (file_exists($tarPath)) {
                File::delete($tarPath);
            }

            if (file_exists($tarGzPath)) {
                File::delete($tarGzPath);
            }

            try {
                $archive = new PharData($tarPath);
                $archive->buildFromDirectory($xmlDirectory);
                unset($archive);

                $archive = new PharData($tarPath);
                $archive->compress(Phar::GZ);
                unset($archive);
            } catch (\Throwable $exception) {
                abort(422, 'No se pudo construir el paquete TAR.GZ para SIAT: '.$exception->getMessage());
            }

            if (! file_exists($tarGzPath)) {
                abort(422, 'No se pudo generar el archivo TAR.GZ del paquete SIAT.');
            }

            $binary = (string) file_get_contents($tarGzPath);

            if ($binary === '') {
                abort(422, 'El archivo TAR.GZ del paquete SIAT se genero vacio.');
            }

            return [
                'binary' => $binary,
                'hash' => hash('sha256', $binary),
                'cantidad' => $facturas->count(),
                'nombre_archivo' => basename($tarGzPath),
            ];
        } finally {
            File::deleteDirectory($workingDirectory);
        }
    }
}
