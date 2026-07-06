<?php

namespace App\Actions\Facturacion;

use App\Helpers\BoliviaPdfHelper;
use App\Models\Configuracion\Empresa;
use App\Models\Factura;

class GenerarPdfFacturaAction
{
    public function __invoke(Factura $factura): array
    {
        BoliviaPdfHelper::bootLibraries();

        $factura->loadMissing([
            'venta.detalle.articulo.unidadMedida',
            'detalles.articulo.unidadMedida',
            'cliente',
            'sucursal',
            'puntoVenta',
            'user',
        ]);

        $empresa = Empresa::query()->first();
        $tipoImpresion = (string) ($factura->puntoVenta?->tipo_impresion ?? 'ticket');

        if ($tipoImpresion === 'carta') {
            $tipoImpresion = 'media_carta';
        }

        $pdf = $tipoImpresion === 'media_carta'
            ? $this->buildMediaCarta($factura, $empresa)
            : $this->buildTicket($factura, $empresa);

        return [
            'content' => BoliviaPdfHelper::output($pdf),
            'filename' => 'factura-'.$factura->numero_factura.'.pdf',
        ];
    }

    private function buildTicket(Factura $factura, ?Empresa $empresa): \FPDF
    {
        $items = $this->resolveItems($factura);
        $subtotal = $this->resolveSubtotal($items);
        $height = max(210, 160 + ($items->count() * 14));
        $pdf = new \FPDF('P', 'mm', [80, $height]);
        $pdf->SetMargins(5, 5, 5);
        $pdf->AddPage();

        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 6, BoliviaPdfHelper::text('FACTURA'), 0, 1, 'C');
        $pdf->SetFont('Arial', '', 8);
        $pdf->Cell(0, 4, BoliviaPdfHelper::text('(CON DERECHO A CREDITO FISCAL)'), 0, 1, 'C');
        $pdf->Cell(0, 4, BoliviaPdfHelper::text($empresa?->razon_social ?: $empresa?->nombre_empresa ?: 'EMPRESA'), 0, 1, 'C');
        $pdf->Cell(0, 4, BoliviaPdfHelper::text($factura->sucursal?->nombre ?: 'CASA MATRIZ'), 0, 1, 'C');
        $pdf->Cell(0, 4, BoliviaPdfHelper::text('Punto de Venta '.($factura->puntoVenta?->codigo ?? 0)), 0, 1, 'C');
        $pdf->MultiCell(0, 4, BoliviaPdfHelper::text($factura->sucursal?->direccion ?: $empresa?->direccion ?: '-'), 0, 'C');
        $pdf->Cell(0, 4, BoliviaPdfHelper::text('Telefono: '.($factura->sucursal?->telefono ?: $empresa?->telefono ?: '-')), 0, 1, 'C');
        $pdf->Ln(1);
        $this->drawSeparator($pdf);

        $this->keyValueLine($pdf, 'NIT Emisor', (string) ($empresa?->nit ?: '-'));
        $this->keyValueLine($pdf, 'Factura N°', (string) ($factura->numero_factura ?: '-'));
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(0, 4, BoliviaPdfHelper::text('Codigo Autorizacion:'), 0, 1, 'L');
        $pdf->SetFont('Arial', '', 8);
        $pdf->MultiCell(0, 4, BoliviaPdfHelper::text((string) ($factura->cuf ?: '-')), 0, 'C');
        $this->drawSeparator($pdf);

        $this->keyValueLine($pdf, 'Nombre/Razon Social', (string) ($factura->cliente?->razon_social ?: $factura->cliente?->nombre ?: '-'));
        $this->keyValueLine($pdf, 'NIT/CI/CEX', trim((string) ($factura->cliente?->nit_ci ?: '-').' '.(string) ($factura->cliente?->complemento ?: '')));
        $this->keyValueLine($pdf, 'Cod. Cliente', (string) ($factura->cliente?->codigo ?: $factura->cliente?->nit_ci ?: $factura->cliente_id));
        $this->keyValueLine($pdf, 'Fecha', optional($factura->fecha_emision)?->timezone(config('app.timezone'))->format('d/m/Y H:i:s') ?: '-');
        $this->drawSeparator($pdf);

        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(0, 4, BoliviaPdfHelper::text('DETALLE'), 0, 1, 'C');
        $pdf->Ln(1);

        foreach ($items as $detalle) {
            $descripcion = trim($this->itemCode($detalle).' - '.$this->itemDescription($detalle));
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->MultiCell(0, 3.5, BoliviaPdfHelper::text($descripcion), 0, 'L');
            $pdf->SetFont('Arial', '', 8);
            $line = sprintf(
                '( %s x %s ) Desc: %s',
                BoliviaPdfHelper::money($this->itemQuantity($detalle)),
                BoliviaPdfHelper::money($this->itemUnitPrice($detalle)),
                BoliviaPdfHelper::money($this->itemDiscount($detalle))
            );
            $pdf->Cell(48, 4, BoliviaPdfHelper::text($line), 0, 0, 'L');
            $pdf->Cell(22, 4, BoliviaPdfHelper::money($this->itemSubtotal($detalle)), 0, 1, 'R');
        }

        $this->drawDottedSeparator($pdf);
        $this->totalLine($pdf, 'Subtotal Bs.', $subtotal);
        $this->totalLine($pdf, 'Desc. global Bs.', (float) ($factura->descuento_global ?? $factura->venta?->descuento));
        $this->totalLine($pdf, 'Total Bs.', (float) $factura->monto_total);
        $this->totalLine($pdf, 'Base Credito Fiscal Bs.', (float) $factura->monto_sujeto_iva);
        $pdf->Ln(2);
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->MultiCell(0, 4, BoliviaPdfHelper::text('SON: '.BoliviaPdfHelper::amountToWords((float) $factura->monto_total)), 0, 'L');
        $this->drawSeparator($pdf);
        $pdf->SetFont('Arial', '', 6.5);
        $pdf->MultiCell(0, 3.5, BoliviaPdfHelper::text('ESTA FACTURA CONTRIBUYE AL DESARROLLO DEL PAIS, EL USO ILICITO SERA SANCIONADO PENALMENTE DE ACUERDO A LEY'), 0, 'C');
        $pdf->MultiCell(0, 3.5, BoliviaPdfHelper::text('Este documento es la Representacion Grafica de un Documento Fiscal Digital emitido en una modalidad de facturacion en linea.'), 0, 'C');

        $qrPath = BoliviaPdfHelper::createQrTempFile(BoliviaPdfHelper::emisorQrUrl($factura, (string) ($empresa?->nit ?: '')));
        $y = $pdf->GetY() + 3;
        $pdf->Image($qrPath, 25, $y, 30, 30);
        @unlink($qrPath);

        return $pdf;
    }

    private function buildMediaCarta(Factura $factura, ?Empresa $empresa): \FPDF
    {
        $items = $this->resolveItems($factura);
        $subtotal = $this->resolveSubtotal($items);
        $descuentoGlobal = (float) ($factura->descuento_global ?? $factura->venta?->descuento);
        $montoGiftCard = (float) ($factura->monto_gift_card ?? 0);
        $montoAPagar = round((float) $factura->monto_total + $montoGiftCard, 2);
        $fechaEmision = optional($factura->fecha_emision)?->timezone(config('app.timezone'))->format('d/m/Y h:i A') ?: '-';
        $documentoId = trim((string) ($factura->cliente?->nit_ci ?: '-').' '.(string) ($factura->cliente?->complemento ?: ''));
        $nombreRazonSocial = (string) ($factura->cliente?->razon_social ?: $factura->cliente?->nombre ?: '-');
        $codigoCliente = (string) ($factura->cliente?->codigo ?: $factura->cliente?->nit_ci ?: $factura->cliente_id);
        $leyenda = (string) ($factura->leyenda ?: 'Ley N° 453: Puedes acceder a la reclamacion cuando tus derechos han sido vulnerados.');
        $leyendaModalidad = $factura->ambiente_facturacion === 'produccion'
            ? '"Este documento es la Representacion Grafica de un Documento Fiscal Digital emitido en una modalidad de facturacion en linea"'
            : '"Este documento es la Representacion Grafica de un Documento Fiscal Digital emitido en una modalidad de facturacion en linea"';

        $pdf = new \FPDF('P', 'mm', 'Letter');
        $pdf->AddPage();

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(70, 5, BoliviaPdfHelper::text($empresa?->razon_social ?: $empresa?->nombre_empresa ?: 'EMPRESA'), 0, 0, 'C');
        $pdf->Cell(30, 5, '', 0, 0, 'C');
        $pdf->Cell(20, 5, '', 0, 0, 'C');
        $pdf->Cell(40, 5, BoliviaPdfHelper::text('NIT'), 0, 0, 'L');
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(40, 5, BoliviaPdfHelper::text((string) ($empresa?->nit ?: '-')), 0, 1, 'L');

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(70, 5, BoliviaPdfHelper::text($factura->sucursal?->nombre ?: 'CASA MATRIZ'), 0, 0, 'C');
        $pdf->Cell(30, 5, '', 0, 0, 'C');
        $pdf->Cell(20, 5, '', 0, 0, 'C');
        $pdf->Cell(40, 5, BoliviaPdfHelper::text('FACTURA N°'), 0, 0, 'L');
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(40, 5, BoliviaPdfHelper::text((string) ($factura->numero_factura ?: '-')), 0, 1, 'L');

        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(70, 5, BoliviaPdfHelper::text('No. Punto de Venta '.($factura->puntoVenta?->codigo ?? 0)), 0, 0, 'C');
        $pdf->Cell(30, 5, '', 0, 0, 'C');
        $pdf->Cell(20, 5, '', 0, 0, 'C');
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(40, 5, BoliviaPdfHelper::text('COD. AUTORIZACIÓN'), 0, 0, 'L');
        $pdf->SetFont('Arial', '', 9);
        $y = $pdf->GetY();
        $pdf->MultiCell(40, 5, BoliviaPdfHelper::text((string) ($factura->cuf ?: '-')), 0, 'L');

        $pdf->SetY($y + 5);
        $pdf->MultiCell(70, 5, BoliviaPdfHelper::text($factura->sucursal?->direccion ?: $empresa?->direccion ?: '-'), 0, 'C');
        $pdf->Cell(70, 5, BoliviaPdfHelper::text('Teléfono: '.($factura->sucursal?->telefono ?: $empresa?->telefono ?: '-')), 0, 1, 'C');
        $pdf->Cell(70, 5, BoliviaPdfHelper::text($factura->sucursal?->ciudad ?? $empresa?->ciudad ?? 'Bolivia'), 0, 1, 'C');

        if ($factura->ambiente_facturacion === 'piloto') {
            $pdf->SetTextColor(215, 215, 215);
            $pdf->SetFont('Arial', 'BI', 36);
            $pdf->SetXY(10, 40);
            $pdf->Cell(190, 16, BoliviaPdfHelper::text('SIN VALOR LEGAL'), 0, 1, 'C');
            $pdf->SetTextColor(0, 0, 0);
        }

        $pdf->Ln(10);
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 5, BoliviaPdfHelper::text('FACTURA'), 0, 1, 'C');
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 5, BoliviaPdfHelper::text('(Con Derecho a Crédito Fiscal)'), 0, 1, 'C');
        $pdf->Ln();

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(40, 5, BoliviaPdfHelper::text('Fecha:'), 0, 0, 'L');
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(60, 5, BoliviaPdfHelper::text($fechaEmision), 0, 0, 'L');
        $pdf->Cell(30, 5, '', 0, 0, 'C');
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(30, 5, BoliviaPdfHelper::text('NIT/CI/CEX:'), 0, 0, 'L');
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(40, 5, BoliviaPdfHelper::text($documentoId), 0, 1, 'L');

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(40, 5, BoliviaPdfHelper::text('Nombre/Razón Social:'), 0, 0, 'L');
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(60, 5, BoliviaPdfHelper::text($nombreRazonSocial), 0, 0, 'L');
        $pdf->Cell(30, 5, '', 0, 0, 'C');
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(30, 5, BoliviaPdfHelper::text('Cod. Cliente:'), 0, 0, 'L');
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(40, 5, BoliviaPdfHelper::text($codigoCliente), 0, 1, 'L');
        $pdf->Ln();

        $pdf->SetFont('Arial', 'B', 9);
        $y = $pdf->GetY();
        $pdf->MultiCell(30, 4, BoliviaPdfHelper::multilineText("CÓDIGO PRODUCTO /\nSERVICIO"), 1, 'C');
        $pdf->SetY($y);
        $pdf->SetX(40);
        $pdf->MultiCell(16, 4, BoliviaPdfHelper::multilineText("\nCANT.\n "), 1, 'C');
        $pdf->SetY($y);
        $pdf->SetX(56);
        $pdf->MultiCell(30, 4, BoliviaPdfHelper::multilineText("\nUNIDAD DE MEDIDA "), 1, 'C');
        $pdf->SetY($y);
        $pdf->SetX(86);
        $pdf->MultiCell(49, 4, BoliviaPdfHelper::multilineText("\nDESCRIPCIÓN \n "), 1, 'C');
        $pdf->SetY($y);
        $pdf->SetX(135);
        $pdf->MultiCell(22, 4, BoliviaPdfHelper::multilineText("\nPRECIO UNITARIO"), 1, 'C');
        $pdf->SetY($y);
        $pdf->SetX(157);
        $pdf->MultiCell(22, 4, BoliviaPdfHelper::multilineText("\nDESCUENTO \n"), 1, 'C');
        $pdf->SetY($y);
        $pdf->SetX(179);
        $pdf->MultiCell(30, 4, BoliviaPdfHelper::multilineText("\nSUBTOTAL \n "), 1, 'C');

        $pdf->SetFont('Arial', '', 8);
        $total = 0.0;
        $tamanoFuente = 4;
        foreach ($items as $detalle) {
            $descripcion = BoliviaPdfHelper::text($this->itemDescription($detalle));
            $lineas = max(1, (int) ceil($pdf->GetStringWidth($descripcion) / 50));
            $alturaMaxima = max($tamanoFuente * $lineas, 8);
            $y = $pdf->GetY();
            $unidad = $this->itemUnit($detalle);

            $pdf->Cell(30, $alturaMaxima, BoliviaPdfHelper::text($this->itemCode($detalle)), 1, 0, 'R');
            $pdf->Cell(16, $alturaMaxima, BoliviaPdfHelper::money($this->itemQuantity($detalle)), 1, 0, 'R');
            $pdf->Cell(30, $alturaMaxima, BoliviaPdfHelper::text($unidad), 1, 0, 'L');
            $pdf->MultiCell(49, $alturaMaxima / $lineas, $descripcion, 1, 'L');
            $pdf->SetY($y);
            $pdf->SetX(135);
            $pdf->Cell(22, $alturaMaxima, BoliviaPdfHelper::money($this->itemUnitPrice($detalle)), 1, 0, 'R');
            $pdf->Cell(22, $alturaMaxima, BoliviaPdfHelper::money($this->itemDiscount($detalle)), 1, 0, 'R');
            $pdf->Cell(30, $alturaMaxima, BoliviaPdfHelper::money($this->itemSubtotal($detalle)), 1, 1, 'R');
            $total += $this->itemSubtotal($detalle);
        }

        $pdf->SetFont('Arial', '', 8);
        $pdf->Cell(125, 4, '', 0, 0, 'L');
        $pdf->Cell(44, 4, 'SUBTOTAL Bs', 1, 0, 'R');
        $pdf->Cell(30, 4, BoliviaPdfHelper::money($subtotal), 1, 1, 'R');
        $pdf->Cell(125, 4, '', 0, 0, 'L');
        $pdf->Cell(44, 4, 'DESCUENTO Bs', 1, 0, 'R');
        $pdf->Cell(30, 4, BoliviaPdfHelper::money($descuentoGlobal), 1, 1, 'R');
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(125, 4, BoliviaPdfHelper::text('Son: '.BoliviaPdfHelper::amountToWords((float) $factura->monto_total)), 0, 0, 'L');
        $pdf->SetFont('Arial', '', 8);
        $pdf->Cell(44, 4, 'TOTAL Bs', 1, 0, 'R');
        $pdf->Cell(30, 4, BoliviaPdfHelper::money((float) $factura->monto_total), 1, 1, 'R');
        $pdf->Cell(125, 4, '', 0, 0, 'L');
        $pdf->Cell(44, 4, 'MONTO GIFT CARD Bs', 1, 0, 'R');
        $pdf->Cell(30, 4, BoliviaPdfHelper::money($montoGiftCard), 1, 1, 'R');
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(125, 4, '', 0, 0, 'L');
        $pdf->Cell(44, 4, 'MONTO A PAGAR Bs', 1, 0, 'R');
        $pdf->Cell(30, 4, BoliviaPdfHelper::money($montoAPagar), 1, 1, 'R');
        $pdf->Cell(125, 4, '', 0, 0, 'L');
        $pdf->SetFont('Arial', 'B', 7);
        $pdf->Cell(44, 4, BoliviaPdfHelper::text('IMPORTE BASE CRÉDITO FISCAL'), 1, 0, 'R');
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(30, 4, BoliviaPdfHelper::money((float) $factura->monto_sujeto_iva), 1, 1, 'R');
        $pdf->Ln(5);

        $y = $pdf->GetY();
        $pdf->SetFont('Arial', '', 7);
        $pdf->Cell(170, 10, BoliviaPdfHelper::text('ESTA FACTURA CONTRIBUYE AL DESARROLLO DEL PAÍS, EL USO ILÍCITO SERÁ SANCIONADO PENALMENTE DE ACUERDO A LEY'), 0, 1, 'C');
        $pdf->Cell(170, 4, BoliviaPdfHelper::text($leyenda), 0, 1, 'C');
        $pdf->MultiCell(170, 4, BoliviaPdfHelper::text($leyendaModalidad), 0, 'C');

        $qrPath = BoliviaPdfHelper::createQrTempFile(BoliviaPdfHelper::emisorQrUrl($factura, (string) ($empresa?->nit ?: '')));
        $pdf->Image($qrPath, 180, $y, 25);
        @unlink($qrPath);

        return $pdf;
    }

    private function keyValueLine(\FPDF $pdf, string $label, string $value): void
    {
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(28, 4, BoliviaPdfHelper::text($label.':'), 0, 0, 'L');
        $pdf->SetFont('Arial', '', 8);
        $pdf->Cell(42, 4, BoliviaPdfHelper::text($value), 0, 1, 'L');
    }

    private function totalLine(\FPDF $pdf, string $label, float $value): void
    {
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(52, 4, BoliviaPdfHelper::text($label), 0, 0, 'R');
        $pdf->SetFont('Arial', '', 8);
        $pdf->Cell(18, 4, BoliviaPdfHelper::money($value), 0, 1, 'R');
    }

    private function drawSeparator(\FPDF $pdf): void
    {
        $pdf->Cell(0, 4, BoliviaPdfHelper::text(str_repeat('-', 74)), 0, 1, 'C');
    }

    private function drawDottedSeparator(\FPDF $pdf): void
    {
        $pdf->Cell(0, 4, BoliviaPdfHelper::text(str_repeat('.', 74)), 0, 1, 'C');
    }

    private function resolveItems(Factura $factura)
    {
        if ($factura->detalles->isNotEmpty()) {
            return $factura->detalles;
        }

        return $factura->venta?->detalle ?? collect();
    }

    private function itemCode(object $detalle): string
    {
        return (string) (
            $detalle->codigo_producto
            ?? $detalle->articulo?->codigo_generico
            ?? ('ART-'.$detalle->articulo_id)
        );
    }

    private function itemDescription(object $detalle): string
    {
        return (string) (
            $detalle->descripcion
            ?? $detalle->articulo?->nombre
            ?? 'Articulo'
        );
    }

    private function itemQuantity(object $detalle): float
    {
        return (float) ($detalle->cantidad ?? 0);
    }

    private function itemUnitPrice(object $detalle): float
    {
        return (float) ($detalle->precio_unitario ?? 0);
    }

    private function itemDiscount(object $detalle): float
    {
        return (float) ($detalle->monto_descuento ?? $detalle->descuento ?? 0);
    }

    private function itemSubtotal(object $detalle): float
    {
        return (float) (
            $detalle->subtotal
            ?? $detalle->total
            ?? max(($this->itemQuantity($detalle) * $this->itemUnitPrice($detalle)) - $this->itemDiscount($detalle), 0)
        );
    }

    private function itemUnit(object $detalle): string
    {
        return (string) (
            $detalle->articulo?->unidadMedida?->nombre
            ?: $detalle->articulo?->unidadMedida?->abreviatura
            ?: ($detalle->unidad_medida ? 'Unidad '.$detalle->unidad_medida : 'Unidad')
        );
    }

    private function resolveSubtotal(iterable $items): float
    {
        $subtotal = 0.0;

        foreach ($items as $item) {
            $subtotal += $this->itemSubtotal($item);
        }

        return round($subtotal, 2);
    }
}
