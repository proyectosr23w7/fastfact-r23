<?php

namespace App\Support;

class DocumentoIdentidad
{
    public static function inferirTipo(?string $documento): ?string
    {
        $documento = trim((string) $documento);

        if ($documento === '' || ! preg_match('/^[0-9]+$/', $documento)) {
            return null;
        }

        $length = strlen($documento);

        if ($length >= 6 && $length <= 8) {
            return '1';
        }

        if ($length >= 9 && $length <= 11) {
            return '5';
        }

        return null;
    }
}
