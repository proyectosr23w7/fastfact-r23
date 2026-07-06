<?php

namespace App\Services\Facturacion;

use App\Actions\Facturacion\GenerarPdfFacturaAction;
use App\Enums\FacturaEstadoEnum;
use App\Mail\FacturaAnulacionRevertidaMail;
use App\Mail\FacturaAnuladaMail;
use App\Mail\FacturaEmitidaMail;
use App\Models\Configuracion\Empresa;
use App\Models\Factura;
use App\Models\FacturaAnulacion;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class FacturaCorreoService
{
    public function __construct(
        private readonly GenerarPdfFacturaAction $generarPdfFactura,
    ) {
    }

    public function enviarFacturaEmitida(Factura $factura): void
    {
        $factura->loadMissing([
            'cliente',
            'venta.detalle.articulo.unidadMedida',
            'detalles.articulo.unidadMedida',
            'sucursal',
            'puntoVenta',
            'user',
        ]);

        if (! $this->puedeEnviar($factura)) {
            return;
        }

        try {
            $pdf = ($this->generarPdfFactura)($factura);
            $xml = (string) $factura->xml_fiscal;
            $xmlFilename = 'factura-'.$factura->numero_factura.'.xml';
            $empresa = Empresa::query()->first();

            Mail::to((string) $factura->cliente->correo)->send(
                new FacturaEmitidaMail(
                    factura: $factura,
                    empresa: $empresa,
                    pdfContent: (string) $pdf['content'],
                    pdfFilename: (string) $pdf['filename'],
                    xmlContent: $xml,
                    xmlFilename: $xmlFilename,
                ),
            );
        } catch (Throwable $exception) {
            Log::warning('No se pudo enviar la factura por correo electronico.', [
                'factura_id' => $factura->id,
                'numero_factura' => $factura->numero_factura,
                'cliente_id' => $factura->cliente_id,
                'correo' => $factura->cliente?->correo,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function enviarAdvertenciaAnulacion(Factura $factura, ?FacturaAnulacion $anulacion = null): void
    {
        $factura->loadMissing(['cliente', 'venta', 'sucursal', 'puntoVenta', 'user']);

        if (! $this->clienteTieneCorreoValido($factura->cliente?->correo)) {
            return;
        }

        try {
            $empresa = Empresa::query()->first();

            Mail::to((string) $factura->cliente->correo)->send(
                new FacturaAnuladaMail(
                    factura: $factura,
                    anulacion: $anulacion,
                    empresa: $empresa,
                ),
            );
        } catch (Throwable $exception) {
            Log::warning('No se pudo enviar la advertencia de anulacion de factura por correo electronico.', [
                'factura_id' => $factura->id,
                'numero_factura' => $factura->numero_factura,
                'cliente_id' => $factura->cliente_id,
                'correo' => $factura->cliente?->correo,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function enviarAdvertenciaReversionAnulacion(Factura $factura): void
    {
        $factura->loadMissing(['cliente', 'venta', 'sucursal', 'puntoVenta', 'user']);

        if (! $this->clienteTieneCorreoValido($factura->cliente?->correo)) {
            return;
        }

        try {
            $empresa = Empresa::query()->first();

            Mail::to((string) $factura->cliente->correo)->send(
                new FacturaAnulacionRevertidaMail(
                    factura: $factura,
                    empresa: $empresa,
                ),
            );
        } catch (Throwable $exception) {
            Log::warning('No se pudo enviar la advertencia de reversion de anulacion por correo electronico.', [
                'factura_id' => $factura->id,
                'numero_factura' => $factura->numero_factura,
                'cliente_id' => $factura->cliente_id,
                'correo' => $factura->cliente?->correo,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function puedeEnviar(Factura $factura): bool
    {
        $estado = is_string($factura->estado_factura)
            ? $factura->estado_factura
            : $factura->estado_factura?->value;

        $correo = trim((string) $factura->cliente?->correo);

        return $estado === FacturaEstadoEnum::EMITIDA->value
            && $this->clienteTieneCorreoValido($correo)
            && filled($factura->xml_fiscal);
    }

    private function clienteTieneCorreoValido(?string $correo): bool
    {
        $correo = trim((string) $correo);

        return $correo !== '' && filter_var($correo, FILTER_VALIDATE_EMAIL) !== false;
    }
}
