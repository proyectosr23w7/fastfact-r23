<?php

namespace App\Actions\Facturacion;

use App\Enums\FacturaEstadoEnum;
use App\Models\Factura;
use App\Services\Facturacion\SiatClientService;

class RegistrarReversionAnulacionFacturaAction
{
    public function __construct(
        private readonly SiatClientService $client,
    ) {
    }

    public function __invoke(Factura $factura, int $userId): Factura
    {
        $response = $this->client->revertirAnulacionFactura([
            'factura_id' => $factura->id,
            'cuf' => $factura->cuf,
        ]);

        if (($response['success'] ?? false) === true) {
            $factura->update([
                'estado_factura' => FacturaEstadoEnum::EMITIDA->value,
                'codigo_estado' => $response['code'] ?? 'REVERSION_ANULACION',
                'descripcion_estado' => $response['message'] ?? 'Reversion de anulacion registrada correctamente.',
                'anulacion_revertida_at' => now(),
                'anulacion_revertida_user_id' => $userId,
                'anulacion_reversion_codigo_respuesta' => $response['code'] ?? null,
                'anulacion_reversion_descripcion' => $response['message'] ?? null,
                'anulacion_reversion_respuesta_siat' => $response,
            ]);
        }

        return $factura->refresh();
    }
}
