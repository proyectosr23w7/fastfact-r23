<?php

namespace App\Enums;

enum TipoImpresionEnum: string
{
    case CARTA = 'carta';
    case MEDIA_CARTA = 'media_carta';
    case TICKET = 'ticket';
    case ROLLO = 'rollo';
    case COMPROBANTE = 'comprobante';
}
