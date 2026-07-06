<?php

namespace App\Enums;

enum TipoDocumentoVentaEnum: string
{
    case NOTA_VENTA = 'nota_venta';
    case FACTURA = 'factura';
    case PROFORMA = 'proforma';
}
