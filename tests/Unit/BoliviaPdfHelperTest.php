<?php

use App\Helpers\BoliviaPdfHelper;
use App\Models\Factura;

uses(Tests\TestCase::class);

it('genera el enlace QR para ticket por defecto', function () {
    config()->set('siat.qr_urls.piloto', 'https://piloto.example/consulta/QR');

    $factura = new Factura([
        'ambiente_facturacion' => 'piloto',
        'cuf' => 'CUF123',
        'numero_factura' => 42,
    ]);

    parse_str((string) parse_url(BoliviaPdfHelper::emisorQrUrl($factura, '123456789'), PHP_URL_QUERY), $query);

    expect($query)->toMatchArray([
        'nit' => '123456789',
        'cuf' => 'CUF123',
        'numero' => '42',
        't' => '1',
    ]);
});

it('genera el enlace QR para media hoja cuando se solicita', function () {
    config()->set('siat.qr_urls.piloto', 'https://piloto.example/consulta/QR');

    $factura = new Factura([
        'ambiente_facturacion' => 'piloto',
        'cuf' => 'CUF456',
        'numero_factura' => 99,
    ]);

    parse_str((string) parse_url(BoliviaPdfHelper::emisorQrUrl($factura, 'NIT 987', 2), PHP_URL_QUERY), $query);

    expect($query['t'])->toBe('2')
        ->and($query['nit'])->toBe('987');
});
