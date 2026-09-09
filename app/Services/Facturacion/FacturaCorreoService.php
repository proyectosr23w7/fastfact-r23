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
use Illuminate\Validation\ValidationException;
use Throwable;

class FacturaCorreoService
{
    public function __construct(
        private readonly GenerarPdfFacturaAction $generarPdfFactura,
    ) {}

    public function enviarFacturaEmitida(Factura $factura): void
    {
        $this->enviarFacturaConContexto($factura, 'emitida');
    }

    public function enviarFacturaFueraLinea(Factura $factura): void
    {
        $this->enviarFacturaConContexto($factura, 'fuera_linea');
    }

    public function enviarConfirmacionFacturaValidada(Factura $factura): void
    {
        $this->enviarFacturaConContexto($factura, 'validada_siat');
    }

    private function enviarFacturaConContexto(Factura $factura, string $contexto): void
    {
        $factura->loadMissing([
            'cliente',
            'detalles.articulo.unidadMedida',
            'sucursal',
            'puntoVenta',
            'user',
        ]);

        if (! $this->puedeEnviar($factura, $contexto)) {
            return;
        }

        try {
            $empresa = Empresa::query()->first();

            $this->sendFacturaEmitida($factura, (string) $factura->cliente->correo, $empresa, $contexto);
        } catch (Throwable $exception) {
            Log::warning('No se pudo enviar la factura por correo electronico.', [
                'factura_id' => $factura->id,
                'numero_factura' => $factura->numero_factura,
                'cliente_id' => $factura->cliente_id,
                'correo' => $factura->cliente?->correo,
                'contexto' => $contexto,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function enviarAdvertenciaAnulacion(Factura $factura, ?FacturaAnulacion $anulacion = null): void
    {
        $factura->loadMissing(['cliente', 'sucursal', 'puntoVenta', 'user']);

        if (! $this->clienteTieneCorreoValido($factura->cliente?->correo)) {
            return;
        }

        try {
            $empresa = Empresa::query()->first();

            $this->sendFacturaAnulada($factura, (string) $factura->cliente->correo, $empresa, $anulacion);
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
        $factura->loadMissing(['cliente', 'sucursal', 'puntoVenta', 'user']);

        if (! $this->clienteTieneCorreoValido($factura->cliente?->correo)) {
            return;
        }

        try {
            $empresa = Empresa::query()->first();

            $this->sendFacturaAnulacionRevertida($factura, (string) $factura->cliente->correo, $empresa);
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

    public function reenviarSegunEstado(Factura $factura, string $correo, bool $actualizarCliente = false): string
    {
        $factura->loadMissing([
            'cliente',
            'detalles.articulo.unidadMedida',
            'sucursal',
            'puntoVenta',
            'user',
            'anulaciones',
        ]);

        $correo = trim($correo);

        if (! $this->clienteTieneCorreoValido($correo)) {
            throw ValidationException::withMessages([
                'correo' => ['Ingresa un correo electronico valido.'],
            ]);
        }

        if ($actualizarCliente && $factura->cliente) {
            $factura->cliente->forceFill(['correo' => $correo])->save();
            $factura->setRelation('cliente', $factura->cliente->refresh());
        }

        $estado = is_string($factura->estado_factura)
            ? $factura->estado_factura
            : $factura->estado_factura?->value;
        $empresa = Empresa::query()->first();

        if (filled($factura->anulacion_revertida_at) && $estado === FacturaEstadoEnum::EMITIDA->value) {
            $this->sendFacturaAnulacionRevertida($factura, $correo, $empresa);

            return 'reversion_anulacion';
        }

        if ($estado === FacturaEstadoEnum::ANULADA->value) {
            $this->sendFacturaAnulada(
                $factura,
                $correo,
                $empresa,
                $factura->anulaciones->sortByDesc('fecha_anulacion')->first(),
            );

            return 'anulacion';
        }

        if ($estado === FacturaEstadoEnum::EMITIDA->value) {
            if (blank($factura->xml_fiscal)) {
                throw ValidationException::withMessages([
                    'factura' => ['La factura no tiene XML fiscal disponible para reenviar.'],
                ]);
            }

            $this->sendFacturaEmitida($factura, $correo, $empresa);

            return 'emision';
        }

        if ($estado === FacturaEstadoEnum::PENDIENTE_ENVIO->value && (int) ($factura->codigo_emision ?? 1) === 2) {
            if (blank($factura->xml_fiscal)) {
                throw ValidationException::withMessages([
                    'factura' => ['La factura no tiene XML fiscal disponible para reenviar.'],
                ]);
            }

            $this->sendFacturaEmitida($factura, $correo, $empresa, 'fuera_linea');

            return 'emision_fuera_linea';
        }

        throw ValidationException::withMessages([
            'estado_factura' => ['Solo se puede reenviar correo para facturas emitidas, emitidas fuera de linea, anuladas o con anulacion revertida.'],
        ]);
    }

    private function sendFacturaEmitida(Factura $factura, string $correo, ?Empresa $empresa, string $contexto = 'emitida'): void
    {
        // El comprobante compacto es solo para entrega impresa. El correo debe
        // conservar siempre la representacion grafica completa de la factura.
        $pdf = ($this->generarPdfFactura)($factura, 'carta');
        $xmlFilename = 'factura-'.$factura->numero_factura.'.xml';

        Mail::to($correo)->send(
            new FacturaEmitidaMail(
                factura: $factura,
                empresa: $empresa,
                pdfContent: (string) $pdf['content'],
                pdfFilename: (string) $pdf['filename'],
                xmlContent: (string) $factura->xml_fiscal,
                xmlFilename: $xmlFilename,
                contexto: $contexto,
            ),
        );
    }

    private function sendFacturaAnulada(
        Factura $factura,
        string $correo,
        ?Empresa $empresa,
        ?FacturaAnulacion $anulacion,
    ): void {
        Mail::to($correo)->send(
            new FacturaAnuladaMail(
                factura: $factura,
                anulacion: $anulacion,
                empresa: $empresa,
            ),
        );
    }

    private function sendFacturaAnulacionRevertida(Factura $factura, string $correo, ?Empresa $empresa): void
    {
        Mail::to($correo)->send(
            new FacturaAnulacionRevertidaMail(
                factura: $factura,
                empresa: $empresa,
            ),
        );
    }

    private function puedeEnviar(Factura $factura, string $contexto = 'emitida'): bool
    {
        $estado = is_string($factura->estado_factura)
            ? $factura->estado_factura
            : $factura->estado_factura?->value;

        $correo = trim((string) $factura->cliente?->correo);

        if (! $this->clienteTieneCorreoValido($correo) || blank($factura->xml_fiscal)) {
            return false;
        }

        if ($contexto === 'fuera_linea') {
            return $estado === FacturaEstadoEnum::PENDIENTE_ENVIO->value
                && (int) ($factura->codigo_emision ?? 1) === 2;
        }

        return $estado === FacturaEstadoEnum::EMITIDA->value;
    }

    private function clienteTieneCorreoValido(?string $correo): bool
    {
        $correo = trim((string) $correo);

        return $correo !== '' && filter_var($correo, FILTER_VALIDATE_EMAIL) !== false;
    }
}
