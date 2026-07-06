<?php

namespace App\Http\Requests\Venta;

use App\Enums\VentaEstadoEnum;
use App\Models\VentaCabecera;
use Illuminate\Foundation\Http\FormRequest;

class AnularVentaRequest extends FormRequest
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

                if ($venta->estado === VentaEstadoEnum::ANULADA) {
                    $validator->errors()->add('estado', 'La venta ya fue anulada.');
                }

                if ($venta->estado !== VentaEstadoEnum::CONFIRMADA) {
                    $validator->errors()->add('estado', 'Solo se pueden anular ventas confirmadas.');
                }
            },
        ];
    }
}
