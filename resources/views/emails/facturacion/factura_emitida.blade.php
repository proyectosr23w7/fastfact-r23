<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Factura emitida</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2933; line-height: 1.5;">
    <p>Estimado cliente,</p>

    <p>
        Adjuntamos la factura {{ $factura->numero_factura }} emitida por
        {{ $empresa?->razon_social ?: $empresa?->nombre_empresa ?: config('app.name', 'FastFact R23') }}.
    </p>

    <p>
        Total: Bs {{ number_format((float) $factura->monto_total, 2, '.', ',') }}<br>
        Fecha: {{ optional($factura->fecha_emision)->timezone(config('app.timezone'))->format('d/m/Y H:i:s') }}
    </p>

    <p>Este correo fue generado automaticamente por {{ config('app.name', 'FastFact R23') }}.</p>
</body>
</html>
