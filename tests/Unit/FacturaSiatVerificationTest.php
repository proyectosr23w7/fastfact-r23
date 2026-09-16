<?php

use App\Services\Facturacion\FacturaService;

uses(Tests\TestCase::class);

function facturaVerificationResult(array $response): bool
{
    $service = (new ReflectionClass(FacturaService::class))->newInstanceWithoutConstructor();
    $method = new ReflectionMethod(FacturaService::class, 'siatConfirmaFacturaValidada');
    $method->setAccessible(true);

    return $method->invoke($service, $response);
}

it('acepta una verificacion SIAT exitosa sin observaciones negativas', function () {
    expect(facturaVerificationResult([
        'success' => true,
        'code' => 'SIAT_OK',
        'message' => 'Operacion SIAT procesada correctamente.',
        'raw' => [
            'RespuestaServicioFacturacion' => [
                'transaccion' => true,
            ],
        ],
    ]))->toBeTrue();
});

it('rechaza una verificacion SIAT que indica factura inexistente', function () {
    expect(facturaVerificationResult([
        'success' => false,
        'code' => '902',
        'message' => 'LA FACTURA O NOTA, NO EXISTE EN LA BASE DE DATOS DEL SIN',
        'raw' => [
            'RespuestaServicioFacturacion' => [
                'transaccion' => false,
                'mensajesList' => [
                    ['descripcion' => 'LA FACTURA O NOTA, NO EXISTE EN LA BASE DE DATOS DEL SIN'],
                ],
            ],
        ],
    ]))->toBeFalse();
});

it('acepta una verificacion SIAT con estado validada explicito', function () {
    expect(facturaVerificationResult([
        'success' => true,
        'code' => '908',
        'message' => 'Factura validada.',
        'raw' => [
            'RespuestaServicioFacturacion' => [
                'codigoDescripcion' => 'VALIDADA',
                'transaccion' => true,
            ],
        ],
    ]))->toBeTrue();
});
