<?php

namespace App\Enums;

enum TipoMovimientoKardexEnum: string
{
    case ENTRADA = 'entrada';
    case SALIDA = 'salida';
    case ANULACION_VENTA = 'anulacion_venta';
}

