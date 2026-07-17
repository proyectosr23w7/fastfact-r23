<?php

namespace App\Helpers;

class SiatMetodoPagoHelper
{
    public static function requiresCardNumber(?string $codigo, ?string $descripcion = null): bool
    {
        $normalizedCode = trim((string) ($codigo ?? ''));
        $normalizedDescription = self::normalizeText($descripcion);

        return $normalizedCode === '2'
            || str_contains($normalizedDescription, 'tarjeta');
    }

    public static function requiresGiftCardAmount(?string $codigo, ?string $descripcion = null): bool
    {
        $normalizedDescription = self::normalizeText($descripcion);

        return str_contains($normalizedDescription, 'gift')
            || str_contains($normalizedDescription, 'gift card')
            || str_contains($normalizedDescription, 'giftcard')
            || str_contains($normalizedDescription, 'tarjeta regalo')
            || str_contains($normalizedDescription, 'vale')
            || str_contains($normalizedDescription, 'prepag');
    }

    public static function normalizeCardNumber(?string $numeroTarjeta): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) ($numeroTarjeta ?? ''));

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 8) {
            return substr($digits, 0, 4).'00000000'.substr($digits, -4);
        }

        return $digits;
    }

    public static function isMaskedCardNumber(?string $numeroTarjeta): bool
    {
        $digits = self::normalizeCardNumber($numeroTarjeta);

        if ($digits === null || strlen($digits) < 16 || strlen($digits) > 19) {
            return false;
        }

        $middle = substr($digits, 4, -4);

        return $middle !== '' && preg_match('/^0+$/', $middle) === 1;
    }

    public static function extractVisibleDigits(?string $numeroTarjeta): array
    {
        $digits = self::normalizeCardNumber($numeroTarjeta);

        if ($digits === null || strlen($digits) < 8) {
            return ['inicio' => '', 'fin' => ''];
        }

        return [
            'inicio' => substr($digits, 0, 4),
            'fin' => substr($digits, -4),
        ];
    }

    public static function normalizeGiftCardAmount(mixed $amount): ?float
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        return round((float) $amount, 2);
    }

    private static function normalizeText(?string $value): string
    {
        $text = mb_strtolower(trim((string) ($value ?? '')));

        return strtr($text, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ñ' => 'n',
        ]);
    }
}
