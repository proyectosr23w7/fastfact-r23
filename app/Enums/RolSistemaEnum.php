<?php

namespace App\Enums;

enum RolSistemaEnum: string
{
    case SUPERADMIN = 'superadmin';
    case ADMINISTRADOR = 'administrador';
    case CAJERO = 'cajero';

    public function label(): string
    {
        return match ($this) {
            self::SUPERADMIN => 'Super Administrador',
            self::ADMINISTRADOR => 'Administrador',
            self::CAJERO => 'Cajero',
        };
    }
}
