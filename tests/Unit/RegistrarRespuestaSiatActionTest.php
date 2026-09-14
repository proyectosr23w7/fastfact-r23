<?php

use App\Actions\Facturacion\RegistrarRespuestaSiatAction;
use App\Enums\FacturaEstadoEnum;
use App\Models\Factura;
use App\Repositories\Facturacion\FacturaRepository;

uses(Tests\TestCase::class);

it('marca como observada una factura cuando SIAT no devuelve headers HTTP', function () {
    $repository = new class extends FacturaRepository
    {
        public array $data = [];

        public function update(Factura $factura, array $data): Factura
        {
            $this->data = $data;

            return $factura->forceFill($data);
        }
    };

    $factura = new Factura([
        'numero_factura' => 10,
        'estado_factura' => FacturaEstadoEnum::PENDIENTE->value,
    ]);

    $action = new RegistrarRespuestaSiatAction($repository);

    $action($factura, [
        'success' => false,
        'code' => 'SOAP_FAULT',
        'message' => 'Error Fetching http headers',
        'debug' => [
            'last_response_headers' => '',
            'last_response' => '',
        ],
    ]);

    expect($repository->data['estado_factura'])->toBe(FacturaEstadoEnum::OBSERVADA->value)
        ->and($repository->data['codigo_estado'])->toBe('SOAP_FAULT');
});
