<?php

use App\Actions\Facturacion\GenerarCufFacturaAction;
use App\Enums\TipoFacturacionEnum;
use App\Models\Cufd;
use Carbon\CarbonImmutable;

it('calcula el CUF con el digito verificador modulo 11 requerido por SIAT', function () {
    $cufd = new Cufd([
        'codigo_control' => '2193FAEF310BF74',
    ]);

    $cabecera = [
        'nitEmisor' => '8449437016',
        'fechaEmision' => CarbonImmutable::create(2026, 7, 6, 22, 58, 26, 'America/La_Paz')->setMillisecond(863),
        'codigoSucursal' => 0,
        'codigoDocumentoSector' => 1,
        'numeroFactura' => 25,
        'codigoPuntoVenta' => 0,
    ];

    $cuf = (new GenerarCufFacturaAction())(
        $cabecera,
        $cufd,
        TipoFacturacionEnum::ELECTRONICA->value,
    );

    expect($cuf)->toBe('2422249F7FBB8EEA0F9B8CA481ECDAB30FBC2C2A5A82193FAEF310BF74');
});
