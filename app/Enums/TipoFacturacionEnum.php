<?php

namespace App\Enums;

enum TipoFacturacionEnum: int
{
    case NO_EMITE = 0;
    case ELECTRONICA = 1;
    case COMPUTARIZADA = 2;

    public function label(): string
    {
        return match ($this) {
            self::NO_EMITE => 'No emite factura',
            self::ELECTRONICA => 'Factura electronica en linea',
            self::COMPUTARIZADA => 'Factura computarizada en linea',
        };
    }
}
