<?php

namespace App\Helpers;

use DOMDocument;
use DOMElement;

class SiatXmlHelper
{
    public static function arrayToXml(string $root, array $payload): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = true;

        $rootElement = $document->createElement($root);
        $document->appendChild($rootElement);

        self::appendArray($document, $rootElement, $payload);

        return $document->saveXML() ?: '';
    }

    public static function hashXml(string $xml): string
    {
        return hash('sha256', $xml);
    }

    private static function appendArray(DOMDocument $document, DOMElement $parent, array $payload): void
    {
        foreach ($payload as $key => $value) {
            $elementName = is_string($key) ? $key : 'item';

            if (is_array($value)) {
                if (array_is_list($value)) {
                    foreach ($value as $listItem) {
                        $child = $document->createElement($elementName);
                        $parent->appendChild($child);

                        if (is_array($listItem)) {
                            self::appendArray($document, $child, $listItem);
                        } else {
                            $child->appendChild($document->createTextNode((string) $listItem));
                        }
                    }

                    continue;
                }

                $child = $document->createElement($elementName);
                $parent->appendChild($child);
                self::appendArray($document, $child, $value);
                continue;
            }

            $child = $document->createElement($elementName);
            $child->appendChild($document->createTextNode((string) $value));
            $parent->appendChild($child);
        }
    }
}
