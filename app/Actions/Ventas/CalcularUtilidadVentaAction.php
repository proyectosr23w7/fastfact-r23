<?php

namespace App\Actions\Ventas;

class CalcularUtilidadVentaAction
{
    public function __invoke(float $totalItem, float $costoTotalItem): float
    {
        return round($totalItem - $costoTotalItem, 2);
    }
}
