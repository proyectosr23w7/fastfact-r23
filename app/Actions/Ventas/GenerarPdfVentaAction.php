<?php

namespace App\Actions\Ventas;

use App\Actions\Facturacion\GenerarPdfFacturaAction;
use App\Enums\TipoDocumentoVentaEnum;
use App\Helpers\BoliviaPdfHelper;
use App\Models\Configuracion\Empresa;
use App\Models\VentaCabecera;

class GenerarPdfVentaAction
{
    public function __construct(
        private readonly GenerarPdfFacturaAction $generarPdfFactura,
    ) {
    }

    public function __invoke(VentaCabecera $venta): array
    {
        if (
            (is_string($venta->tipo_documento_venta) ? $venta->tipo_documento_venta : $venta->tipo_documento_venta?->value) === TipoDocumentoVentaEnum::FACTURA->value
            && $venta->factura
        ) {
            return ($this->generarPdfFactura)($venta->factura);
        }

        BoliviaPdfHelper::bootLibraries();

        $venta->loadMissing(['cliente', 'sucursal', 'puntoVenta', 'user', 'detalle.articulo.unidadMedida', 'factura']);
        $empresa = Empresa::query()->first();
        $tipoImpresion = (string) ($venta->puntoVenta?->tipo_impresion ?? 'ticket');
        if ($tipoImpresion === 'carta') {
            $tipoImpresion = 'media_carta';
        }

        $pdf = $tipoImpresion === 'media_carta'
            ? $this->buildMediaCarta($venta, $empresa)
            : $this->buildTicket($venta, $empresa);

        return [
            'content' => BoliviaPdfHelper::output($pdf),
            'filename' => 'venta-'.$venta->numero_venta.'.pdf',
        ];
    }

    private function buildTicket(VentaCabecera $venta, ?Empresa $empresa): \FPDF
    {
        $items = $venta->detalle ?? collect();
        $subtotal = $this->resolveSubtotal($items);
        $height = max(180, 145 + ($items->count() * 14));
        $pdf = new \FPDF('P', 'mm', [80, $height]);
        $pdf->SetMargins(5, 5, 5);
        $pdf->AddPage();

        $title = $this->documentLabel($venta);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 6, BoliviaPdfHelper::text($title), 0, 1, 'C');
        $pdf->SetFont('Arial', '', 8);
        $pdf->Cell(0, 4, BoliviaPdfHelper::text($empresa?->razon_social ?: $empresa?->nombre_empresa ?: 'EMPRESA'), 0, 1, 'C');
        $pdf->Cell(0, 4, BoliviaPdfHelper::text($venta->sucursal?->nombre ?: 'CASA MATRIZ'), 0, 1, 'C');
        $pdf->MultiCell(0, 4, BoliviaPdfHelper::text($venta->sucursal?->direccion ?: $empresa?->direccion ?: '-'), 0, 'C');
        $pdf->Cell(0, 4, BoliviaPdfHelper::text('Telefono: '.($venta->sucursal?->telefono ?: $empresa?->telefono ?: '-')), 0, 1, 'C');
        $this->drawSeparator($pdf);

        $this->keyValueLine($pdf, 'Documento', $venta->numero_venta);
        $this->keyValueLine($pdf, 'Fecha', optional($venta->fecha_venta)?->format('d/m/Y').' '.optional($venta->updated_at)?->timezone(config('app.timezone'))->format('H:i:s'));
        $this->keyValueLine($pdf, 'Cliente', (string) ($venta->cliente?->razon_social ?: $venta->cliente?->nombre ?: '-'));
        $this->keyValueLine($pdf, 'NIT/CI', trim((string) ($venta->cliente?->nit_ci ?: '-').' '.(string) ($venta->cliente?->complemento ?: '')));
        $this->keyValueLine($pdf, 'Metodo pago', $this->metodoPagoDescripcion((string) ($venta->codigo_metodo_pago ?: '')));
        $this->drawSeparator($pdf);

        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(0, 4, BoliviaPdfHelper::text('DETALLE'), 0, 1, 'C');
        $pdf->Ln(1);

        foreach ($items as $detalle) {
            $descripcion = trim(($detalle->articulo?->codigo_generico ?: 'ART-'.$detalle->articulo_id).' - '.($detalle->articulo?->nombre ?: 'Articulo'));
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->MultiCell(0, 3.5, BoliviaPdfHelper::text($descripcion), 0, 'L');
            $pdf->SetFont('Arial', '', 8);
            $line = sprintf(
                '%s x %s',
                BoliviaPdfHelper::money($detalle->cantidad),
                BoliviaPdfHelper::money($detalle->precio_unitario)
            );
            $pdf->Cell(40, 4, BoliviaPdfHelper::text($line), 0, 0, 'L');
            $pdf->Cell(30, 4, BoliviaPdfHelper::money($detalle->total), 0, 1, 'R');
        }

        $this->drawDottedSeparator($pdf);
        $this->totalLine($pdf, 'Subtotal Bs.', $subtotal);
        $this->totalLine($pdf, 'Desc. global Bs.', (float) $venta->descuento);
        $this->totalLine($pdf, 'Total Bs.', (float) $venta->total);
        $pdf->Ln(2);
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->MultiCell(0, 4, BoliviaPdfHelper::text('SON: '.BoliviaPdfHelper::amountToWords((float) $venta->total)), 0, 'L');
        if ($venta->observacion) {
            $this->drawSeparator($pdf);
            $pdf->SetFont('Arial', '', 7);
            $pdf->MultiCell(0, 3.5, BoliviaPdfHelper::text('Obs.: '.$venta->observacion), 0, 'L');
        }

        return $pdf;
    }

    private function buildMediaCarta(VentaCabecera $venta, ?Empresa $empresa): \FPDF
    {
        $pdf = new \FPDF('P', 'mm', 'Letter');
        $pdf->SetMargins(10, 10, 10);
        $pdf->AddPage();

        $this->renderVentaHeader($pdf, $venta, $empresa);
        $this->renderVentaDetailTable($pdf, $venta);
        $this->renderVentaTotals($pdf, $venta);

        return $pdf;
    }

    private function renderVentaHeader(\FPDF $pdf, VentaCabecera $venta, ?Empresa $empresa): void
    {
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 6, BoliviaPdfHelper::text($this->documentLabel($venta)), 0, 1, 'C');
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(0, 5, BoliviaPdfHelper::text($empresa?->razon_social ?: $empresa?->nombre_empresa ?: 'EMPRESA'), 0, 1, 'C');
        $pdf->Cell(0, 4, BoliviaPdfHelper::text($venta->sucursal?->nombre ?: 'CASA MATRIZ'), 0, 1, 'C');
        $pdf->MultiCell(0, 4, BoliviaPdfHelper::text($venta->sucursal?->direccion ?: $empresa?->direccion ?: '-'), 0, 'C');
        $pdf->Cell(0, 4, BoliviaPdfHelper::text('Telefono: '.($venta->sucursal?->telefono ?: $empresa?->telefono ?: '-')), 0, 1, 'C');
        $pdf->Ln(2);

        $this->ventaDataLine($pdf, 'Numero', $venta->numero_venta, 'Fecha', optional($venta->fecha_venta)?->format('d/m/Y'));
        $this->ventaDataLine($pdf, 'Cliente', (string) ($venta->cliente?->razon_social ?: $venta->cliente?->nombre ?: '-'), 'NIT/CI', trim((string) ($venta->cliente?->nit_ci ?: '-').' '.(string) ($venta->cliente?->complemento ?: '')));
        $this->ventaDataLine($pdf, 'Punto Venta', (string) ($venta->puntoVenta?->nombre ?: '-'), 'Metodo Pago', $this->metodoPagoDescripcion((string) ($venta->codigo_metodo_pago ?: '')));
        if ($venta->user?->name) {
            $this->ventaDataLine($pdf, 'Usuario', (string) $venta->user->name, 'Estado', is_string($venta->estado) ? $venta->estado : $venta->estado?->value);
        }
        $pdf->Ln(2);
    }

    private function renderVentaDetailTable(\FPDF $pdf, VentaCabecera $venta): void
    {
        $pdf->SetFont('Arial', 'B', 8);
        $widths = [24, 16, 76, 22, 18, 29];
        $headers = ['CODIGO', 'CANT.', 'DESCRIPCION', 'P. UNIT', 'DESC', 'SUBTOTAL'];
        $leftX = $pdf->GetX();

        foreach ($headers as $index => $header) {
            $pdf->Cell($widths[$index], 6, BoliviaPdfHelper::text($header), 1, $index === array_key_last($headers) ? 1 : 0, 'C');
        }

        $pdf->SetFont('Arial', '', 8);
        foreach (($venta->detalle ?? collect()) as $detalle) {
            $descripcion = BoliviaPdfHelper::text((string) ($detalle->articulo?->nombre ?: 'Articulo'));
            $y = $pdf->GetY();
            $lineas = max(1, (int) ceil(strlen($descripcion) / 46));
            $height = max(6, $lineas * 4);

            $pdf->Cell($widths[0], $height, BoliviaPdfHelper::text((string) ($detalle->articulo?->codigo_generico ?: 'ART-'.$detalle->articulo_id)), 1, 0, 'L');
            $pdf->Cell($widths[1], $height, BoliviaPdfHelper::money($detalle->cantidad), 1, 0, 'R');
            $pdf->MultiCell($widths[2], 4, $descripcion, 1, 'L');
            $pdf->SetXY($leftX + array_sum(array_slice($widths, 0, 3)), $y);
            $pdf->Cell($widths[3], $height, BoliviaPdfHelper::money($detalle->precio_unitario), 1, 0, 'R');
            $pdf->Cell($widths[4], $height, BoliviaPdfHelper::money($detalle->descuento), 1, 0, 'R');
            $pdf->Cell($widths[5], $height, BoliviaPdfHelper::money($detalle->total), 1, 1, 'R');
        }
    }

    private function renderVentaTotals(\FPDF $pdf, VentaCabecera $venta): void
    {
        $subtotal = $this->resolveSubtotal($venta->detalle ?? collect());
        $pdf->SetFont('Arial', '', 8);
        $pdf->Cell(86, 5, '', 0, 0);
        $pdf->Cell(22, 5, 'SUBTOTAL Bs', 1, 0, 'R');
        $pdf->Cell(22, 5, BoliviaPdfHelper::money($subtotal), 1, 1, 'R');
        $pdf->Cell(86, 5, '', 0, 0);
        $pdf->Cell(22, 5, 'DESC. GLOB.', 1, 0, 'R');
        $pdf->Cell(22, 5, BoliviaPdfHelper::money($venta->descuento), 1, 1, 'R');
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(86, 5, BoliviaPdfHelper::text('SON: '.BoliviaPdfHelper::amountToWords((float) $venta->total)), 0, 0, 'L');
        $pdf->SetFont('Arial', '', 8);
        $pdf->Cell(22, 5, 'TOTAL Bs', 1, 0, 'R');
        $pdf->Cell(22, 5, BoliviaPdfHelper::money($venta->total), 1, 1, 'R');

        if ($venta->observacion) {
            $pdf->Ln(3);
            $pdf->SetFont('Arial', '', 7.5);
            $pdf->MultiCell(0, 4, BoliviaPdfHelper::text('Observacion: '.$venta->observacion), 0, 'L');
        }
    }

    private function ventaDataLine(\FPDF $pdf, string $leftLabel, string $leftValue, string $rightLabel, string $rightValue): void
    {
        $pdf->SetFont('Arial', 'B', 8.5);
        $pdf->Cell(24, 5, BoliviaPdfHelper::text($leftLabel.':'), 0, 0, 'L');
        $pdf->SetFont('Arial', '', 8.5);
        $pdf->Cell(46, 5, BoliviaPdfHelper::text($leftValue), 0, 0, 'L');
        $pdf->SetFont('Arial', 'B', 8.5);
        $pdf->Cell(24, 5, BoliviaPdfHelper::text($rightLabel.':'), 0, 0, 'L');
        $pdf->SetFont('Arial', '', 8.5);
        $pdf->Cell(40, 5, BoliviaPdfHelper::text($rightValue), 0, 1, 'L');
    }

    private function keyValueLine(\FPDF $pdf, string $label, string $value): void
    {
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(26, 4, BoliviaPdfHelper::text($label.':'), 0, 0, 'L');
        $pdf->SetFont('Arial', '', 8);
        $pdf->Cell(44, 4, BoliviaPdfHelper::text($value), 0, 1, 'L');
    }

    private function totalLine(\FPDF $pdf, string $label, float $value): void
    {
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(52, 4, BoliviaPdfHelper::text($label), 0, 0, 'R');
        $pdf->SetFont('Arial', '', 8);
        $pdf->Cell(18, 4, BoliviaPdfHelper::money($value), 0, 1, 'R');
    }

    private function metodoPagoDescripcion(string $codigo): string
    {
        if ($codigo === '') {
            return '-';
        }

        return (string) (\App\Models\SinMetodoPago::query()
            ->where('codigo_clasificador', $codigo)
            ->value('descripcion') ?: $codigo);
    }

    private function documentLabel(VentaCabecera $venta): string
    {
        $value = is_string($venta->tipo_documento_venta) ? $venta->tipo_documento_venta : $venta->tipo_documento_venta?->value;

        return match ($value) {
            TipoDocumentoVentaEnum::PROFORMA->value => 'PROFORMA',
            default => BoliviaPdfHelper::documentTitle((string) $value),
        };
    }

    private function drawSeparator(\FPDF $pdf): void
    {
        $pdf->Cell(0, 4, BoliviaPdfHelper::text(str_repeat('-', 74)), 0, 1, 'C');
    }

    private function drawDottedSeparator(\FPDF $pdf): void
    {
        $pdf->Cell(0, 4, BoliviaPdfHelper::text(str_repeat('.', 74)), 0, 1, 'C');
    }

    private function resolveSubtotal(iterable $items): float
    {
        $subtotal = 0.0;

        foreach ($items as $item) {
            $cantidad = (float) ($item->cantidad ?? 0);
            $precioUnitario = (float) ($item->precio_unitario ?? 0);
            $descuento = (float) ($item->descuento ?? 0);

            $subtotal += max(($cantidad * $precioUnitario) - $descuento, 0);
        }

        return round($subtotal, 2);
    }
}
