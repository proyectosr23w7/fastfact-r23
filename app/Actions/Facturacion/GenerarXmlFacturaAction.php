<?php

namespace App\Actions\Facturacion;

use App\Helpers\SiatXmlHelper;
use DOMDocument;
use DOMElement;
class GenerarXmlFacturaAction
{
    /**
     * Campos monetarios/decimales que conviene serializar con formato estable
     * para evitar observaciones del SIAT por valores enteros o sin escala.
     *
     * @var array<int, string>
     */
    private array $decimalFields = [
        'cantidad',
        'precioUnitario',
        'montoDescuento',
        'subTotal',
        'montoTotal',
        'montoTotalSujetoIva',
        'tipoCambio',
        'montoTotalMoneda',
        'montoGiftCard',
        'descuentoAdicional',
    ];

    /**
     * Campos opcionales del XSD que deben omitirse por completo cuando no tienen valor,
     * en lugar de serializarse como xsi:nil.
     *
     * @var array<int, string>
     */
    private array $omitWhenEmpty = [
        'montoGiftCard',
        'descuentoAdicional',
        'codigoExcepcion',
    ];

    public function __construct(
        private readonly ValidarXmlFacturaXsdAction $validarXmlFacturaXsd,
    ) {
    }

    public function __invoke(array $payload, int $ventaId): array
    {
        $xml = $this->buildXml($payload);
        $schemaPath = $this->resolveSchemaPath($payload['root'] ?? '');
        $validation = ($this->validarXmlFacturaXsd)($xml, $schemaPath);

        if (! $validation['valid']) {
            abort(422, 'El XML generado no cumple el XSD SIAT: '.implode(' | ', $validation['errors']));
        }

        return [
            'xml' => $xml,
            'path' => null,
            'hash' => SiatXmlHelper::hashXml($xml),
            'schema_path' => $schemaPath,
            'filename' => "factura-venta-{$ventaId}.xml",
        ];
    }

    private function buildXml(array $payload): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = false;
        $document->xmlStandalone = true;

        $rootName = (string) ($payload['root'] ?? 'facturaElectronicaCompraVenta');
        $schemaLocation = (string) ($payload['schema_location'] ?? 'facturaElectronicaCompraVenta.xsd');
        $root = $document->createElement($rootName);
        $root->setAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'xsi:noNamespaceSchemaLocation', $schemaLocation);
        $document->appendChild($root);

        $this->appendArray($document, $root, [
            'cabecera' => $payload['cabecera'] ?? [],
            'detalle' => $this->detalleFiscal($payload['detalle'] ?? []),
        ]);

        return $document->saveXML() ?: '';
    }

    private function detalleFiscal(array $detalles): array
    {
        $camposPermitidos = [
            'actividadEconomica',
            'codigoProductoSin',
            'codigoProducto',
            'descripcion',
            'cantidad',
            'unidadMedida',
            'precioUnitario',
            'montoDescuento',
            'subTotal',
            'numeroSerie',
            'numeroImei',
        ];

        return array_map(
            fn (array $detalle): array => array_intersect_key($detalle, array_flip($camposPermitidos)),
            $detalles,
        );
    }

    private function appendArray(DOMDocument $document, DOMElement $parent, array $payload): void
    {
        foreach ($payload as $key => $value) {
            $name = is_string($key) ? $key : 'item';

            if ($this->shouldOmitElement($name, $value)) {
                continue;
            }

            if (is_array($value) && array_is_list($value)) {
                foreach ($value as $item) {
                    $child = $document->createElement($name);
                    $parent->appendChild($child);

                if (is_array($item)) {
                    $this->appendArray($document, $child, $item);
                } else {
                    $this->appendValue($document, $child, $item, $name);
                }
                }

                continue;
            }

            $child = $document->createElement($name);
            $parent->appendChild($child);

            if (is_array($value)) {
                $this->appendArray($document, $child, $value);
                continue;
            }

            $this->appendValue($document, $child, $value, $name);
        }
    }

    private function appendValue(DOMDocument $document, DOMElement $element, mixed $value, ?string $fieldName = null): void
    {
        if ($value === null || $value === '') {
            $element->setAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'xsi:nil', 'true');

            return;
        }

        if ($value instanceof \DateTimeInterface) {
            $element->appendChild($document->createTextNode($value->format('Y-m-d\TH:i:s.v')));

            return;
        }

        if ($fieldName !== null && in_array($fieldName, $this->decimalFields, true) && is_numeric($value)) {
            $element->appendChild($document->createTextNode(number_format((float) $value, 2, '.', '')));

            return;
        }

        $element->appendChild($document->createTextNode((string) $value));
    }

    private function shouldOmitElement(string $name, mixed $value): bool
    {
        if (! in_array($name, $this->omitWhenEmpty, true)) {
            return false;
        }

        return $value === null || $value === '';
    }

    private function resolveSchemaPath(string $rootName): string
    {
        return $rootName === 'facturaComputarizadaCompraVenta'
            ? (string) config('siat.files.xsd.factura_computarizada_compra_venta')
            : (string) config('siat.files.xsd.factura_electronica_compra_venta');
    }
}
