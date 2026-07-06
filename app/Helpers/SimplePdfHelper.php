<?php

namespace App\Helpers;

class SimplePdfHelper
{
    /**
     * @param  array<int, string>  $lines
     */
    public static function buildFromLines(array $lines, string $title = 'Documento'): string
    {
        $normalizedLines = array_values(array_filter(array_map(
            fn (string $line) => self::normalizeText($line),
            $lines
        ), fn (string $line) => $line !== ''));

        $contentLines = [];
        $y = 800;

        foreach ($normalizedLines as $line) {
            if ($y < 60) {
                break;
            }

            $contentLines[] = sprintf('BT /F1 11 Tf 50 %d Td (%s) Tj ET', $y, self::escapePdfText($line));
            $y -= 16;
        }

        $content = implode("\n", $contentLines);
        $contentLength = strlen($content);
        $safeTitle = self::escapePdfText(self::normalizeText($title));

        $objects = [
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj",
            "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj",
            "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj",
            "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>\nendobj",
            "5 0 obj\n<< /Length {$contentLength} >>\nstream\n{$content}\nendstream\nendobj",
            "6 0 obj\n<< /Title ({$safeTitle}) /Producer (Sistema Ventas) >>\nendobj",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object."\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        for ($index = 1; $index <= count($objects); $index++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$index]);
        }

        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R /Info 6 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }

    private static function escapePdfText(string $value): string
    {
        return str_replace(
            ['\\', '(', ')'],
            ['\\\\', '\\(', '\\)'],
            $value,
        );
    }

    private static function normalizeText(string $value): string
    {
        $clean = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        return trim($clean !== false ? preg_replace('/[^\x20-\x7E]/', '', $clean) ?? '' : $value);
    }
}
