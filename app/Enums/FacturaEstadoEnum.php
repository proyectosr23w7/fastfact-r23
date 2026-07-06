<?php

namespace App\Enums;

enum FacturaEstadoEnum: string
{
    case PENDIENTE = 'pendiente';
    case PENDIENTE_ENVIO = 'pendiente_envio';
    case EMITIDA = 'emitida';
    case OBSERVADA = 'observada';
    case ANULADA = 'anulada';
    case RECHAZADA = 'rechazada';
}
