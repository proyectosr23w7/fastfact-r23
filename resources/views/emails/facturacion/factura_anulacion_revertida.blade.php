<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reversion de anulacion de factura</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2933; line-height: 1.5;">
    <p>Estimado cliente,</p>

    <p>
        Le informamos que se revirtio la anulacion de la factura {{ $factura->numero_factura }} emitida por
        {{ $empresa?->razon_social ?: $empresa?->nombre_empresa ?: config('app.name', 'FastFact R23') }}.
        La factura vuelve a estar valida.
    </p>

    <p>
        CUF: {{ $factura->cuf }}<br>
        Total: Bs {{ number_format((float) $factura->monto_total, 2, '.', ',') }}<br>
        Fecha de emision: {{ optional($factura->fecha_emision)->timezone(config('app.timezone'))->format('d/m/Y H:i:s') }}<br>
        Fecha de reversion: {{ optional($factura->anulacion_revertida_at)->timezone(config('app.timezone'))->format('d/m/Y H:i:s') }}
    </p>

    <p>
        Esta operacion restablece la validez de la factura y no permite una nueva anulacion posterior del mismo documento fiscal.
    </p>

    <p>Este correo fue generado automaticamente por {{ config('app.name', 'FastFact R23') }}.</p>
</body>
</html>
