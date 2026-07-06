<?php

namespace App\Actions\Facturacion;

use App\Enums\FacturaEstadoEnum;
use App\Models\Factura;
use App\Models\FacturaAnulacion;
use App\Services\Facturacion\SiatClientService;

class RegistrarAnulacionFacturaAction
{
    public function __construct(
        private readonly SiatClientService $client,
    ) {
    }

    public function __invoke(Factura $factura, array $data, int $userId): FacturaAnulacion
    {
        $response = $this->client->anularFactura([
            'factura_id' => $factura->id,
            'codigo_motivo_anulacion' => $data['codigo_motivo_anulacion'],
            'cuf' => $factura->cuf,
            'codigo_recepcion' => $factura->codigo_recepcion,
        ]);

        $anulacion = $factura->anulaciones()->create([
            'codigo_motivo_anulacion' => $data['codigo_motivo_anulacion'],
            'descripcion_motivo' => $data['descripcion_motivo'] ?? null,
            'fecha_anulacion' => now(),
            'codigo_respuesta' => $response['code'] ?? null,
            'descripcion_respuesta' => $response['message'] ?? null,
            'datos_respuesta_siat' => $response,
            'user_id' => $userId,
        ]);

        if (($response['success'] ?? false) === true) {
            $factura->update([
                'estado_factura' => FacturaEstadoEnum::ANULADA->value,
                'codigo_estado' => $response['code'] ?? 'ANULADA',
                'descripcion_estado' => $response['message'] ?? 'Factura anulada correctamente.',
            ]);
        }

        return $anulacion->refresh();
    }
}
