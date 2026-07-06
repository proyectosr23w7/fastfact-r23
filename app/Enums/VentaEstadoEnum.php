<?php

namespace App\Enums;

enum VentaEstadoEnum: string
{
    case BORRADOR = 'borrador';
    case CONFIRMADA = 'confirmada';
    case ANULADA = 'anulada';
}
