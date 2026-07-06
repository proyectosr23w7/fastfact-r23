<?php

namespace App\Enums;

enum SiatSincronizacionEstadoEnum: string
{
    case PENDIENTE = 'pendiente';
    case EXITOSA = 'exitosa';
    case OBSERVADA = 'observada';
    case ERROR = 'error';
}
