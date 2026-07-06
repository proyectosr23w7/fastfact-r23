<?php

namespace App\Actions\Facturacion;

use App\Models\Factura;
use App\Services\Facturacion\SiatClientService;

class ConsultarEstadoFacturaAction
{
    public function __construct(
        private readonly SiatClientService $client,
    ) {
    }

    public function __invoke(Factura $factura): array
    {
        return $this->client->consultarFactura([
            'factura_id' => $factura->id,
            'venta_id' => $factura->venta_id,
            'codigo_recepcion' => $factura->codigo_recepcion,
            'cuf' => $factura->cuf,
            'cufd_id' => $factura->cufd_id,
        ]);
    }
}
