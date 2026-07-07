<?php

namespace App\Actions\Facturacion;

use App\Enums\TipoFacturacionEnum;
use App\Models\Cufd;
use Carbon\CarbonInterface;

class GenerarCufFacturaAction
{
    public function __invoke(array $cabecera, Cufd $cufd, int $tipoFacturacion, int $codigoEmision = 1): string
    {
        $codigoControl = trim((string) $cufd->codigo_control);

        if ($codigoControl === '') {
            abort(422, 'El CUFD vigente no cuenta con codigo de control para generar el CUF.');
        }

        $fechaEmision = $cabecera['fechaEmision'] ?? null;

        if (! $fechaEmision instanceof CarbonInterface) {
            abort(422, 'La fecha de emision no es valida para generar el CUF.');
        }

        $cadena = implode('', [
            $this->padNumeric($cabecera['nitEmisor'] ?? '', 13),
            $fechaEmision->format('YmdHisv'),
            $this->padNumeric($cabecera['codigoSucursal'] ?? 0, 4),
            $this->modalidadCode($tipoFacturacion),
            $this->padNumeric($codigoEmision, 1),
            '1',
            $this->padNumeric($cabecera['codigoDocumentoSector'] ?? 1, 2),
            $this->padNumeric($cabecera['numeroFactura'] ?? 0, 10),
            $this->padNumeric($cabecera['codigoPuntoVenta'] ?? 0, 4),
        ]);

        $cadena .= $this->modulo11($cadena);

        return $this->base10To16($cadena).$codigoControl;
    }

    private function padNumeric(string|int|float $value, int $length): string
    {
        $numeric = preg_replace('/\D+/', '', (string) $value) ?? '';

        return str_pad($numeric, $length, '0', STR_PAD_LEFT);
    }

    private function modalidadCode(int $tipoFacturacion): string
    {
        return match ($tipoFacturacion) {
            TipoFacturacionEnum::ELECTRONICA->value => '1',
            TipoFacturacionEnum::COMPUTARIZADA->value => '2',
            default => '2',
        };
    }

    private function modulo11(string $cadena): string
    {
        $suma = 0;
        $multiplicador = 2;

        for ($i = strlen($cadena) - 1; $i >= 0; $i--) {
            $suma += ((int) $cadena[$i]) * $multiplicador;
            $multiplicador++;

            if ($multiplicador > 9) {
                $multiplicador = 2;
            }
        }

        $digito = 11 - ($suma % 11);

        return match ($digito) {
            10 => '1',
            11 => '0',
            default => (string) $digito,
        };
    }

    private function base10To16(string $decimal): string
    {
        $decimal = ltrim($decimal, '0');

        if ($decimal === '') {
            return '0';
        }

        $hex = '';
        $map = '0123456789ABCDEF';

        while ($decimal !== '0') {
            $quotient = '';
            $remainder = 0;

            foreach (str_split($decimal) as $digit) {
                $accumulator = ($remainder * 10) + (int) $digit;
                $partial = intdiv($accumulator, 16);
                $remainder = $accumulator % 16;

                if ($quotient !== '' || $partial > 0) {
                    $quotient .= (string) $partial;
                }
            }

            $hex = $map[$remainder].$hex;
            $decimal = $quotient === '' ? '0' : $quotient;
        }

        return $hex;
    }
}
