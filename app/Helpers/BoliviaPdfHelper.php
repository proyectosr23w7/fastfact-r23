<?php

namespace App\Helpers;

use App\Models\Factura;
use NumberFormatter;

class BoliviaPdfHelper
{
    public static function bootLibraries(): void
    {
        if (! class_exists('FPDF')) {
            require_once app_path('Libraries/fpdf/fpdf.php');
        }

        if (! class_exists('QRcode')) {
            require_once app_path('Libraries/qrlib/phpqrcode.php');
        }
    }

    public static function text(?string $value): string
    {
        $text = trim((string) ($value ?? ''));

        if ($text === '') {
            return '';
        }

        $normalized = iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $text);

        return $normalized !== false ? $normalized : $text;
    }

    public static function multilineText(?string $value): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", (string) ($value ?? ''));

        if ($text === '') {
            return '';
        }

        $normalized = iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $text);

        return $normalized !== false ? $normalized : $text;
    }

    public static function money(float|int|string|null $value): string
    {
        return number_format((float) ($value ?? 0), 2, '.', '');
    }

    public static function amountToWords(float $amount): string
    {
        $formatter = new NumberFormatter('es_BO', NumberFormatter::SPELLOUT);
        $whole = (int) floor($amount);
        $decimal = (int) round(($amount - $whole) * 100);

        return strtoupper(trim($formatter->format($whole).' '.str_pad((string) $decimal, 2, '0', STR_PAD_LEFT).'/100 BOLIVIANOS'));
    }

    public static function emisorQrUrl(Factura $factura, string $nitEmisor): string
    {
        $base = (string) config('siat.qr_urls.'.($factura->ambiente_facturacion ?: 'piloto'), config('siat.qr_urls.piloto'));

        return $base.'?'.http_build_query([
            'nit' => preg_replace('/\D+/', '', $nitEmisor),
            'cuf' => (string) $factura->cuf,
            'numero' => (string) $factura->numero_factura,
            't' => 2,
        ]);
    }

    public static function createQrTempFile(string $content): string
    {
        self::bootLibraries();

        $path = tempnam(sys_get_temp_dir(), 'siat_qr_');

        if ($path === false) {
            throw new \RuntimeException('No se pudo crear el archivo temporal del QR.');
        }

        $pngPath = $path.'.png';
        @unlink($path);

        \QRcode::png($content, $pngPath, QR_ECLEVEL_L, 4, 1);

        return $pngPath;
    }

    public static function output(\FPDF $pdf): string
    {
        return $pdf->Output('S');
    }

    public static function documentTitle(string $value): string
    {
        return strtoupper(str_replace('_', ' ', $value));
    }
}
