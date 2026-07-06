<?php

namespace App\Enums;

enum SiatCatalogoEnum: string
{
    case ACTIVIDADES = 'actividades';
    case PRODUCTOS_SERVICIOS = 'productos_servicios';
    case MOTIVOS_ANULACION = 'motivos_anulacion';
    case LEYENDAS = 'leyendas';
    case DOCUMENTOS_IDENTIDAD = 'documentos_identidad';
    case UNIDADES_MEDIDA = 'unidades_medida';
    case MONEDAS = 'monedas';
    case METODOS_PAGO = 'metodos_pago';
}
