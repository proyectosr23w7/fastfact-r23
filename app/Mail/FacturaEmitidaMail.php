<?php

namespace App\Mail;

use App\Models\Configuracion\Empresa;
use App\Models\Factura;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class FacturaEmitidaMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Factura $factura,
        public readonly ?Empresa $empresa,
        private readonly string $pdfContent,
        private readonly string $pdfFilename,
        private readonly string $xmlContent,
        private readonly string $xmlFilename,
        public readonly string $contexto = 'emitida',
    ) {}

    public function build(): self
    {
        $numero = $this->factura->numero_factura ?: $this->factura->id;
        $empresa = $this->empresa?->razon_social ?: $this->empresa?->nombre_empresa ?: config('app.name', 'FastFact R23');
        $subjectPrefix = match ($this->contexto) {
            'fuera_linea' => 'Factura emitida fuera de linea',
            'validada_siat' => 'Factura validada por SIAT',
            default => 'Factura electronica',
        };

        return $this
            ->subject("{$subjectPrefix} Nro. {$numero} - {$empresa}")
            ->view('emails.facturacion.factura_emitida')
            ->attachData($this->pdfContent, $this->pdfFilename, [
                'mime' => 'application/pdf',
            ])
            ->attachData($this->xmlContent, $this->xmlFilename, [
                'mime' => 'application/xml',
            ]);
    }
}
