<?php

namespace App\Enums;

enum AmbienteFacturacionEnum: string
{
    case PRODUCCION = 'produccion';
    case PILOTO = 'piloto';

    public function code(): int
    {
        return match ($this) {
            self::PRODUCCION => 1,
            self::PILOTO => 2,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::PRODUCCION => 'Produccion',
            self::PILOTO => 'Piloto',
        };
    }
}
