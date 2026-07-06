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
}
