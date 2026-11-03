<?php

namespace App\Http\Requests\Concerns;

use App\Models\Configuracion\Configuracion;

trait ConvierteTextosAMayusculas
{
    protected function convertirTextosAMayusculas(array $campos): void
    {
        if (! (bool) (Configuracion::current()?->textos_mayusculas_habilitados ?? false)) {
            return;
        }

        $textos = [];
        foreach ($campos as $campo) {
            $valor = $this->input($campo);
            if (is_string($valor)) {
                $textos[$campo] = mb_strtoupper($valor, 'UTF-8');
            }
        }

        $this->merge($textos);
    }
}
