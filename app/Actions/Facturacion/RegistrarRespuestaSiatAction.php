<?php

namespace App\Actions\Facturacion;

use App\Enums\FacturaEstadoEnum;
use App\Models\Factura;
use App\Repositories\Facturacion\FacturaRepository;

class RegistrarRespuestaSiatAction
{
    public function __construct(
        private readonly FacturaRepository $repository,
    ) {
    }

    public function __invoke(Factura $factura, array $response): Factura
    {
        $estado = match (true) {
            ($response['success'] ?? false) === true => FacturaEstadoEnum::EMITIDA->value,
            ($response['code'] ?? null) === 'ADAPTER_PENDING' => FacturaEstadoEnum::OBSERVADA->value,
            $this->isTransientTransportError($response) => FacturaEstadoEnum::OBSERVADA->value,
            default => FacturaEstadoEnum::RECHAZADA->value,
        };

        return $this->repository->update($factura, [
            'codigo_estado' => (string) ($response['code'] ?? ''),
            'descripcion_estado' => $this->buildDescription($response),
            'estado_factura' => $estado,
            'codigo_recepcion' => $response['codigo_recepcion'] ?? $factura->codigo_recepcion,
            'cuf' => $response['cuf'] ?? $factura->cuf,
            'datos_respuesta_siat' => $response,
            'fecha_emision' => $factura->fecha_emision ?: now(),
        ]);
    }

    private function buildDescription(array $response): string
    {
        $message = (string) ($response['message'] ?? 'Sin respuesta disponible.');
        $candidate = (string) ($response['candidate'] ?? '');
        $operation = (string) ($response['operation'] ?? '');
        $endpoint = (string) ($response['endpoint']['wsdl'] ?? '');

        $parts = array_filter([
            $message,
            $candidate !== '' ? "candidate={$candidate}" : null,
            $operation !== '' ? "operation={$operation}" : null,
            $endpoint !== '' ? "wsdl={$endpoint}" : null,
        ]);

        return mb_substr(implode(' | ', $parts), 0, 1000);
    }

    private function isTransientTransportError(array $response): bool
    {
        $code = (string) ($response['code'] ?? '');

        if (! in_array($code, ['SOAP_FAULT', 'SOAP_CLIENT_ERROR'], true)) {
            return false;
        }

        $message = mb_strtolower((string) ($response['message'] ?? ''));
        $lastResponse = (string) ($response['debug']['last_response'] ?? '');
        $lastResponseHeaders = (string) ($response['debug']['last_response_headers'] ?? '');

        if ($lastResponse === '' && $lastResponseHeaders === '') {
            return true;
        }

        return str_contains($message, 'error fetching http headers')
            || str_contains($message, 'timeout')
            || str_contains($message, 'timed out')
            || str_contains($message, 'could not connect')
            || str_contains($message, 'failed to open stream');
    }
}
