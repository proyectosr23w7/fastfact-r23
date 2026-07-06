<?php

namespace App\Actions\Facturacion;

use App\Services\Facturacion\SiatClientService;

class EnviarFacturaSiatAction
{
    public function __construct(
        private readonly SiatClientService $client,
    ) {
    }

    public function __invoke(array $payload): array
    {
        return $this->client->emitirFactura($payload);
    }
}
