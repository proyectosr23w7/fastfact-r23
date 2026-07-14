<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Factura anulada</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2933; line-height: 1.5;">
    <p>Estimado cliente:</p>

    <p>
        Le informamos que la factura electronica Nro.
        <strong>{{ $factura->numero_factura }}</strong>, emitida por
        <strong>{{ $empresa?->razon_social ?: $empresa?->nombre_empresa ?: config('app.name', 'FastFact R23') }}</strong>,
        fue anulada de acuerdo con el procedimiento fiscal correspondiente.
    </p>

    <p>
        CUF: {{ $factura->cuf ?: 'No disponible' }}<br>
        Total original: Bs {{ number_format((float) $factura->monto_total, 2, '.', ',') }}<br>
        Fecha de emision: {{ optional($factura->fecha_emision)->timezone(config('app.timezone'))->format('d/m/Y H:i:s') }}<br>
        Fecha de anulacion: {{ optional($anulacion?->fecha_anulacion)->timezone(config('app.timezone'))->format('d/m/Y H:i:s') }}
    </p>

    @if ($anulacion?->descripcion_motivo)
        <p>Motivo: {{ $anulacion->descripcion_motivo }}</p>
    @endif

    <p>
        Este mensaje fue generado automaticamente por {{ config('app.name', 'FastFact R23') }}.
        Si requiere mayor informacion, por favor contactese con el emisor de la factura.
    </p>
</body>
</html>
