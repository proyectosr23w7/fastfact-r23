<?php

namespace App\Enums;

enum ModuloSistemaEnum: string
{
    case CONFIGURACION = 'configuracion';
    case SEGURIDAD = 'seguridad';
    case INVENTARIO = 'inventario';
    case VENTAS = 'ventas';
    case REPORTES = 'reportes';
    case FACTURACION = 'facturacion';

    public function label(): string
    {
        return match ($this) {
            self::CONFIGURACION => 'ConfiguraciÃ³n',
            self::SEGURIDAD => 'Seguridad',
            self::INVENTARIO => 'Inventario',
            self::VENTAS => 'Ventas',
            self::REPORTES => 'Reportes',
            self::FACTURACION => 'FacturaciÃ³n',
        };
    }

    public static function options(): array
    {
        return array_map(
            fn (self $modulo) => [
                'label' => $modulo->label(),
                'value' => $modulo->value,
            ],
            self::cases(),
        );
    }
}

