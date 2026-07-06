<?php

namespace App\Mail;

use App\Models\Configuracion\Empresa;
use App\Models\Factura;
use App\Models\FacturaAnulacion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class FacturaAnuladaMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Factura $factura,
        public readonly ?FacturaAnulacion $anulacion,
        public readonly ?Empresa $empresa,
    ) {
    }

    public function build(): self
    {
        $numero = $this->factura->numero_factura ?: $this->factura->id;
        $empresa = $this->empresa?->razon_social ?: $this->empresa?->nombre_empresa ?: config('app.name', 'FastFact R23');

        return $this
            ->subject("Advertencia: factura {$numero} anulada - {$empresa}")
            ->view('emails.facturacion.factura_anulada');
    }
}
