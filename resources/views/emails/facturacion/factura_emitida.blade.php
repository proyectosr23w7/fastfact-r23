<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Factura emitida</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2933; line-height: 1.5;">
    <p>Estimado cliente:</p>

    <p>
        Por medio del presente le hacemos llegar la factura electronica Nro.
        <strong>{{ $factura->numero_factura }}</strong>, emitida por
        <strong>{{ $empresa?->razon_social ?: $empresa?->nombre_empresa ?: config('app.name', 'FastFact R23') }}</strong>.
        Adjuntamos el documento en formato PDF y el XML fiscal correspondiente.
    </p>

    <p>
        CUF: {{ $factura->cuf ?: 'No disponible' }}<br>
        Total: Bs {{ number_format((float) $factura->monto_total, 2, '.', ',') }}<br>
        Fecha: {{ optional($factura->fecha_emision)->timezone(config('app.timezone'))->format('d/m/Y H:i:s') }}
    </p>

    <p>
        Este mensaje fue generado automaticamente por {{ config('app.name', 'FastFact R23') }}.
        Si requiere alguna aclaracion, por favor contactese con el emisor de la factura.
    </p>
</body>
</html>
