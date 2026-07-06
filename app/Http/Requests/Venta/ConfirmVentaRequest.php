<?php

namespace App\Http\Requests\Venta;

use App\Enums\VentaEstadoEnum;
use App\Models\VentaCabecera;
use App\Services\Ventas\VentaService;
use Illuminate\Foundation\Http\FormRequest;

class ConfirmVentaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'observacion' => ['nullable', 'string'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator) {
                $venta = $this->route('venta');

                if (! $venta instanceof VentaCabecera) {
                    return;
                }

                if ($venta->estado !== VentaEstadoEnum::BORRADOR) {
                    $validator->errors()->add('estado', 'Solo se pueden confirmar ventas en borrador.');
                    return;
                }

                if (! $venta->detalle()->exists()) {
                    $validator->errors()->add('detalle', 'La venta debe tener al menos un detalle.');
                    return;
                }

                try {
                    app(VentaService::class)->stockSuficiente($venta->load('detalle'));
                } catch (\Throwable $exception) {
                    $validator->errors()->add('stock', $exception->getMessage());
                }
            },
        ];
    }
}
