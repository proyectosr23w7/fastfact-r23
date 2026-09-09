<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Factura N° {{ $factura->numero_factura }}</title>
    <style>
        :root { color-scheme: light; font-family: Arial, sans-serif; color: #17211a; background: #f3f6f4; }
        body { margin: 0; padding: 24px 16px; }
        main { max-width: 620px; margin: auto; background: white; border: 1px solid #dce5df; border-radius: 16px; padding: 28px; box-shadow: 0 12px 35px rgba(20, 50, 30, .08); }
        h1 { margin: 0 0 6px; font-size: 24px; }
        .muted { color: #607066; }
        dl { display: grid; grid-template-columns: 150px 1fr; gap: 10px; margin: 24px 0; }
        dt { font-weight: 700; }
        dd { margin: 0; overflow-wrap: anywhere; }
        .total { padding: 18px; border-radius: 12px; background: #edf8f0; font-size: 22px; font-weight: 700; text-align: center; }
        .actions { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 22px; }
        a { padding: 12px; border-radius: 9px; background: #176b3a; color: white; text-align: center; text-decoration: none; font-weight: 700; }
        @media (max-width: 520px) { main { padding: 20px; } dl { grid-template-columns: 1fr; gap: 4px; } dd { margin-bottom: 8px; } .actions { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<main>
    <p class="muted">{{ $empresa?->razon_social ?: $empresa?->nombre_empresa ?: 'Empresa' }}</p>
    <h1>Factura N° {{ $factura->numero_factura }}</h1>
    <p class="muted">Consulta segura del documento fiscal</p>

    <dl>
        <dt>Fecha</dt><dd>{{ optional($factura->fecha_emision)?->timezone(config('app.timezone'))->format('d/m/Y H:i:s') ?: '-' }}</dd>
        <dt>Cliente</dt><dd>{{ $factura->cliente?->razon_social ?: $factura->cliente?->nombre ?: '-' }}</dd>
        <dt>NIT/CI/CEX</dt><dd>{{ $factura->cliente?->nit_ci ?: '-' }}</dd>
        <dt>CUF</dt><dd>{{ $factura->cuf }}</dd>
        <dt>Estado</dt><dd>{{ strtoupper(is_string($factura->estado_factura) ? $factura->estado_factura : $factura->estado_factura?->value) }}</dd>
    </dl>

    <div class="total">Total: Bs. {{ number_format((float) $factura->monto_total, 2, '.', '') }}</div>

    <div class="actions">
        <a href="{{ URL::signedRoute('facturas.publicas.pdf', ['factura' => $factura]) }}">Ver factura en rollo</a>
        <a href="{{ URL::signedRoute('facturas.publicas.xml', ['factura' => $factura]) }}">Ver XML fiscal</a>
    </div>
</main>
</body>
</html>
