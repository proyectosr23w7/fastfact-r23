<?php

namespace App\Actions\Facturacion;

use DOMDocument;

class ValidarXmlFacturaXsdAction
{
    public function __invoke(string $xml, string $schemaPath): array
    {
        libxml_use_internal_errors(true);
        libxml_clear_errors();

        $document = new DOMDocument();
        $loaded = $document->loadXML($xml);
        $valid = $loaded && $document->schemaValidate($schemaPath);

        $errors = collect(libxml_get_errors())
            ->map(fn (\LibXMLError $error) => trim($error->message).' (linea '.$error->line.')')
            ->values()
            ->all();

        libxml_clear_errors();
        libxml_use_internal_errors(false);

        return [
            'valid' => $valid,
            'errors' => $errors,
        ];
    }
}
