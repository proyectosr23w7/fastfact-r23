<?php

use App\Helpers\SiatMetodoPagoHelper;

test('detecta metodos gift card por descripcion sin confundir transferencia', function () {
    expect(SiatMetodoPagoHelper::requiresGiftCardAmount('7', 'Transferencia bancaria'))->toBeFalse()
        ->and(SiatMetodoPagoHelper::requiresGiftCardAmount('99', 'Gift Card'))->toBeTrue()
        ->and(SiatMetodoPagoHelper::requiresGiftCardAmount('99', 'Vale de consumo'))->toBeTrue()
        ->and(SiatMetodoPagoHelper::requiresGiftCardAmount('99', 'Tarjeta regalo'))->toBeTrue()
        ->and(SiatMetodoPagoHelper::requiresGiftCardAmount('1', 'Efectivo'))->toBeFalse();
});

test('detecta tarjeta y normaliza el numero para siat', function () {
    expect(SiatMetodoPagoHelper::requiresCardNumber('2', 'Efectivo'))->toBeTrue()
        ->and(SiatMetodoPagoHelper::requiresCardNumber('99', 'Tarjeta de debito'))->toBeTrue()
        ->and(SiatMetodoPagoHelper::normalizeCardNumber('1234-9876'))->toBe('1234000000009876')
        ->and(SiatMetodoPagoHelper::isMaskedCardNumber('1234000000009876'))->toBeTrue();
});
