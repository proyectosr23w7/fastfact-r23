<?php

namespace App\Services\Reportes;

use App\Enums\FacturaEstadoEnum;
use App\Helpers\BoliviaPdfHelper;
use App\Models\Factura;
use App\Models\FacturaDetalle;
use App\Models\SinMetodoPago;
use App\Models\User;
use App\Support\OperationalContextScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ReporteFacturacionService
{
    public function generar(array $filters, ?User $user = null): array
    {
        $filters = $this->normalizarFiltros($filters, $user);
        $baseQuery = $this->baseFacturasQuery($filters, $user);
        $facturasValidasQuery = $this->soloFacturasOperativas(clone $baseQuery);

        return [
            'resumen' => $this->resumen(clone $baseQuery, clone $facturasValidasQuery),
            'por_estado' => $this->porEstado(clone $baseQuery),
            'por_metodo_pago' => $this->porMetodoPago(clone $baseQuery),
            'por_usuario' => $this->porUsuario(clone $baseQuery),
            'por_producto' => $this->porProducto($filters, $user),
            'facturas_recientes' => $this->facturasRecientes(clone $baseQuery),
            'meta' => $this->meta($filters, $user),
        ];
    }

    public function exportarExcel(array $filters, ?User $user = null): array
    {
        $reporte = $this->generar($filters, $user);

        return [
            'content' => $this->buildExcelXml($reporte),
            'filename' => 'reporte-facturacion-'.$reporte['meta']['filtros']['fecha_desde'].'-'.$reporte['meta']['filtros']['fecha_hasta'].'.xls',
        ];
    }

    public function exportarPdf(array $filters, ?User $user = null): array
    {
        BoliviaPdfHelper::bootLibraries();

        $reporte = $this->generar($filters, $user);
        $filtros = $reporte['meta']['filtros'];
        $pdf = new \FPDF('L', 'mm', 'Letter');
        $pdf->SetMargins(10, 10, 10);
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 8, BoliviaPdfHelper::text('Reporte de facturacion'), 0, 1, 'L');
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(0, 5, BoliviaPdfHelper::text('Periodo: '.$filtros['fecha_desde'].' al '.$filtros['fecha_hasta'].' | Generado: '.now()->format('Y-m-d H:i:s')), 0, 1, 'L');
        $pdf->Ln(3);

        $this->pdfSummary($pdf, $reporte['resumen']);
        $this->pdfTable($pdf, 'Metodos de pago', ['Metodo', 'Facturas', 'Total Bs'], collect($reporte['por_metodo_pago'])->map(fn ($row) => [
            $row['descripcion'],
            $this->number($row['cantidad']),
            $this->money($row['total']),
        ])->all(), [110, 35, 35]);
        $this->pdfTable($pdf, 'Usuarios', ['Usuario', 'Facturas', 'Total Bs'], collect($reporte['por_usuario'])->map(fn ($row) => [
            $row['usuario'],
            $this->number($row['cantidad']),
            $this->money($row['total']),
        ])->all(), [110, 35, 35]);

        $pdf->AddPage();
        $this->pdfTable($pdf, 'Productos facturados', ['Codigo', 'Producto', 'Cantidad', 'Precio prom.', 'Total Bs'], collect($reporte['por_producto'])->map(fn ($row) => [
            $row['codigo_producto'],
            $row['descripcion'],
            $this->number($row['cantidad'], 2),
            $this->money($row['precio_promedio']),
            $this->money($row['total']),
        ])->all(), [32, 115, 28, 32, 32]);
        $this->pdfTable($pdf, 'Facturas recientes', ['Nro.', 'Fecha', 'Cliente', 'Usuario', 'Estado', 'Total Bs'], collect($reporte['facturas_recientes'])->map(fn ($row) => [
            $row['numero_factura'],
            $row['fecha_emision'],
            $row['cliente'],
            $row['usuario'],
            $row['estado_factura'],
            $this->money($row['monto_total']),
        ])->all(), [18, 38, 80, 52, 28, 28]);

        return [
            'content' => BoliviaPdfHelper::output($pdf),
            'filename' => 'reporte-facturacion-'.$filtros['fecha_desde'].'-'.$filtros['fecha_hasta'].'.pdf',
        ];
    }

    private function buildExcelXml(array $reporte): string
    {
        $filtros = $reporte['meta']['filtros'];
        $worksheets = [
            $this->excelWorksheet('Resumen', [
                ['Indicador', 'Valor'],
                ['Periodo', $filtros['fecha_desde'].' al '.$filtros['fecha_hasta']],
                ['Facturas registradas', $reporte['resumen']['facturas_total']],
                ['Facturas operativas', $reporte['resumen']['facturas_operativas']],
                ['Total operativo Bs', $reporte['resumen']['monto_total']],
                ['Monto sujeto a IVA Bs', $reporte['resumen']['monto_sujeto_iva']],
                ['Descuentos Bs', $reporte['resumen']['descuentos']],
                ['Anuladas', $reporte['resumen']['anuladas']],
                ['Pendientes', $reporte['resumen']['pendientes']],
                ['Observadas', $reporte['resumen']['observadas']],
            ]),
            $this->excelWorksheet('Metodos de pago', array_merge(
                [['Codigo', 'Metodo', 'Facturas', 'Total Bs']],
                array_map(fn ($row) => [$row['codigo'], $row['descripcion'], $row['cantidad'], $row['total']], $reporte['por_metodo_pago']),
            )),
            $this->excelWorksheet('Usuarios', array_merge(
                [['Usuario', 'Facturas', 'Total Bs']],
                array_map(fn ($row) => [$row['usuario'], $row['cantidad'], $row['total']], $reporte['por_usuario']),
            )),
            $this->excelWorksheet('Productos', array_merge(
                [['Codigo', 'Producto', 'Cantidad', 'Precio promedio Bs', 'Total Bs']],
                array_map(fn ($row) => [$row['codigo_producto'], $row['descripcion'], $row['cantidad'], $row['precio_promedio'], $row['total']], $reporte['por_producto']),
            )),
            $this->excelWorksheet('Facturas', array_merge(
                [['Nro.', 'Fecha', 'Cliente', 'Usuario', 'Sucursal', 'Punto de venta', 'Estado', 'Metodo pago', 'Total Bs']],
                array_map(fn ($row) => [
                    $row['numero_factura'],
                    $row['fecha_emision'],
                    $row['cliente'],
                    $row['usuario'],
                    $row['sucursal'],
                    $row['punto_venta'],
                    $row['estado_factura'],
                    $row['codigo_metodo_pago'],
                    $row['monto_total'],
                ], $reporte['facturas_recientes']),
            )),
        ];

        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<?mso-application progid="Excel.Sheet"?>'."\n"
            .'<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">'
            .'<Styles><Style ss:ID="header"><Font ss:Bold="1"/><Interior ss:Color="#DFF3E6" ss:Pattern="Solid"/></Style></Styles>'
            .implode('', $worksheets)
            .'</Workbook>';
    }

    private function excelWorksheet(string $name, array $rows): string
    {
        $xml = '<Worksheet ss:Name="'.$this->xml($name).'"><Table>';

        foreach ($rows as $index => $row) {
            $xml .= '<Row>';

            foreach ($row as $cell) {
                $isNumeric = is_int($cell) || is_float($cell);
                $style = $index === 0 ? ' ss:StyleID="header"' : '';
                $type = $isNumeric ? 'Number' : 'String';
                $value = $isNumeric ? (string) $cell : $this->xml((string) ($cell ?? ''));
                $xml .= '<Cell'.$style.'><Data ss:Type="'.$type.'">'.$value.'</Data></Cell>';
            }

            $xml .= '</Row>';
        }

        return $xml.'</Table></Worksheet>';
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    private function pdfSummary(\FPDF $pdf, array $resumen): void
    {
        $items = [
            ['Facturas registradas', $this->number($resumen['facturas_total'])],
            ['Facturas operativas', $this->number($resumen['facturas_operativas'])],
            ['Total operativo Bs', $this->money($resumen['monto_total'])],
            ['Monto sujeto IVA Bs', $this->money($resumen['monto_sujeto_iva'])],
            ['Anuladas', $this->number($resumen['anuladas'])],
            ['Pendientes', $this->number($resumen['pendientes'])],
            ['Observadas', $this->number($resumen['observadas'])],
        ];

        $pdf->SetFont('Arial', 'B', 9);

        foreach ($items as [$label, $value]) {
            $pdf->Cell(55, 7, BoliviaPdfHelper::text($label), 1, 0, 'L');
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(35, 7, BoliviaPdfHelper::text($value), 1, 1, 'R');
            $pdf->SetFont('Arial', 'B', 9);
        }

        $pdf->Ln(5);
    }

    private function pdfTable(\FPDF $pdf, string $title, array $headers, array $rows, array $widths): void
    {
        if ($pdf->GetY() > 160) {
            $pdf->AddPage();
        }

        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(0, 7, BoliviaPdfHelper::text($title), 0, 1, 'L');
        $pdf->SetFont('Arial', 'B', 8);

        foreach ($headers as $index => $header) {
            $pdf->Cell($widths[$index], 7, BoliviaPdfHelper::text((string) $header), 1, 0, 'L');
        }

        $pdf->Ln();
        $pdf->SetFont('Arial', '', 8);

        foreach (array_slice($rows, 0, 30) as $row) {
            if ($pdf->GetY() > 190) {
                $pdf->AddPage();
                $pdf->SetFont('Arial', '', 8);
            }

            foreach ($row as $index => $cell) {
                $align = $index >= count($row) - 2 ? 'R' : 'L';
                $pdf->Cell($widths[$index], 6, BoliviaPdfHelper::text(mb_strimwidth((string) ($cell ?? ''), 0, 55, '...')), 1, 0, $align);
            }

            $pdf->Ln();
        }

        if ($rows === []) {
            $pdf->Cell(array_sum($widths), 7, BoliviaPdfHelper::text('Sin datos para el periodo seleccionado.'), 1, 1, 'C');
        }

        $pdf->Ln(5);
    }

    private function money(mixed $value): string
    {
        return number_format((float) ($value ?? 0), 2, '.', ',');
    }

    private function number(mixed $value, int $decimals = 0): string
    {
        return number_format((float) ($value ?? 0), $decimals, '.', ',');
    }

    private function normalizarFiltros(array $filters, ?User $user): array
    {
        $filters = OperationalContextScope::mergeFilters($filters, $user);

        $filters['fecha_desde'] = $this->parseDate($filters['fecha_desde'] ?? null)
            ?? now()->startOfMonth();
        $filters['fecha_hasta'] = $this->parseDate($filters['fecha_hasta'] ?? null)
            ?? now();

        if ($filters['fecha_hasta']->lt($filters['fecha_desde'])) {
            $filters['fecha_hasta'] = $filters['fecha_desde']->copy()->endOfDay();
        }

        return $filters;
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function baseFacturasQuery(array $filters, ?User $user): Builder
    {
        $query = Factura::query()
            ->with(['cliente:id,nombre,razon_social,nit_ci', 'sucursal:id,codigo,nombre', 'puntoVenta:id,sucursal_id,codigo,nombre', 'user:id,name,email'])
            ->whereBetween('facturas.fecha_emision', [
                $filters['fecha_desde']->copy()->startOfDay(),
                $filters['fecha_hasta']->copy()->endOfDay(),
            ]);

        return $query
            ->when(
                ! OperationalContextScope::isGlobal($user) && $user?->punto_venta_id,
                fn ($query) => $query->where('facturas.punto_venta_id', $user->punto_venta_id),
            )
            ->when(
                ! OperationalContextScope::isGlobal($user) && ! $user?->punto_venta_id && $user?->sucursal_id,
                fn ($query) => $query->where('facturas.sucursal_id', $user->sucursal_id),
            )
            ->when(filled($filters['sucursal_id'] ?? null), fn ($query) => $query->where('facturas.sucursal_id', $filters['sucursal_id']))
            ->when(filled($filters['punto_venta_id'] ?? null), fn ($query) => $query->where('facturas.punto_venta_id', $filters['punto_venta_id']))
            ->when(filled($filters['user_id'] ?? null), fn ($query) => $query->where('facturas.user_id', $filters['user_id']))
            ->when(filled($filters['estado_factura'] ?? null), fn ($query) => $query->where('facturas.estado_factura', $filters['estado_factura']))
            ->when(filled($filters['codigo_metodo_pago'] ?? null), fn ($query) => $query->where('facturas.codigo_metodo_pago', $filters['codigo_metodo_pago']));
    }

    private function soloFacturasOperativas(Builder $query): Builder
    {
        return $query->whereNotIn('estado_factura', [
            FacturaEstadoEnum::ANULADA->value,
            FacturaEstadoEnum::RECHAZADA->value,
        ]);
    }

    private function resumen(Builder $baseQuery, Builder $validasQuery): array
    {
        return [
            'facturas_total' => (int) (clone $baseQuery)->count(),
            'facturas_operativas' => (int) (clone $validasQuery)->count(),
            'monto_total' => round((float) (clone $validasQuery)->sum('monto_total'), 2),
            'monto_sujeto_iva' => round((float) (clone $validasQuery)->sum('monto_sujeto_iva'), 2),
            'descuentos' => round((float) (clone $validasQuery)->sum('descuento_global'), 2),
            'anuladas' => (int) (clone $baseQuery)->where('estado_factura', FacturaEstadoEnum::ANULADA->value)->count(),
            'observadas' => (int) (clone $baseQuery)->where('estado_factura', FacturaEstadoEnum::OBSERVADA->value)->count(),
            'pendientes' => (int) (clone $baseQuery)->whereIn('estado_factura', [
                FacturaEstadoEnum::PENDIENTE->value,
                FacturaEstadoEnum::PENDIENTE_ENVIO->value,
            ])->count(),
        ];
    }

    private function porEstado(Builder $query): array
    {
        return $query
            ->select('estado_factura', DB::raw('COUNT(*) as cantidad'), DB::raw('COALESCE(SUM(monto_total), 0) as total'))
            ->groupBy('estado_factura')
            ->orderBy('estado_factura')
            ->get()
            ->map(fn ($row) => [
                'estado' => $this->estadoValue($row->estado_factura),
                'cantidad' => (int) $row->cantidad,
                'total' => round((float) $row->total, 2),
            ])
            ->values()
            ->all();
    }

    private function porMetodoPago(Builder $query): array
    {
        $metodos = SinMetodoPago::query()
            ->pluck('descripcion', 'codigo_clasificador')
            ->mapWithKeys(fn ($descripcion, $codigo) => [(string) $codigo => $descripcion]);

        return $this->soloFacturasOperativas($query)
            ->select('codigo_metodo_pago', DB::raw('COUNT(*) as cantidad'), DB::raw('COALESCE(SUM(monto_total), 0) as total'))
            ->groupBy('codigo_metodo_pago')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'codigo' => (string) $row->codigo_metodo_pago,
                'descripcion' => (string) ($metodos[(string) $row->codigo_metodo_pago] ?? 'Metodo '.$row->codigo_metodo_pago),
                'cantidad' => (int) $row->cantidad,
                'total' => round((float) $row->total, 2),
            ])
            ->values()
            ->all();
    }

    private function porUsuario(Builder $query): array
    {
        return $this->soloFacturasOperativas($query)
            ->leftJoin('users', 'users.id', '=', 'facturas.user_id')
            ->select('facturas.user_id', DB::raw("COALESCE(users.name, 'Sin usuario') as usuario"), DB::raw('COUNT(*) as cantidad'), DB::raw('COALESCE(SUM(facturas.monto_total), 0) as total'))
            ->groupBy('facturas.user_id', 'users.name')
            ->orderByDesc('total')
            ->limit(20)
            ->get()
            ->map(fn ($row) => [
                'user_id' => $row->user_id ? (int) $row->user_id : null,
                'usuario' => (string) $row->usuario,
                'cantidad' => (int) $row->cantidad,
                'total' => round((float) $row->total, 2),
            ])
            ->values()
            ->all();
    }

    private function porProducto(array $filters, ?User $user): array
    {
        $query = FacturaDetalle::query()
            ->join('facturas', 'facturas.id', '=', 'factura_detalles.factura_id')
            ->whereBetween('facturas.fecha_emision', [
                $filters['fecha_desde']->copy()->startOfDay(),
                $filters['fecha_hasta']->copy()->endOfDay(),
            ])
            ->whereNotIn('facturas.estado_factura', [
                FacturaEstadoEnum::ANULADA->value,
                FacturaEstadoEnum::RECHAZADA->value,
            ]);

        return $query
            ->when(
                ! OperationalContextScope::isGlobal($user) && $user?->punto_venta_id,
                fn ($query) => $query->where('facturas.punto_venta_id', $user->punto_venta_id),
            )
            ->when(
                ! OperationalContextScope::isGlobal($user) && ! $user?->punto_venta_id && $user?->sucursal_id,
                fn ($query) => $query->where('facturas.sucursal_id', $user->sucursal_id),
            )
            ->when(filled($filters['sucursal_id'] ?? null), fn ($query) => $query->where('facturas.sucursal_id', $filters['sucursal_id']))
            ->when(filled($filters['punto_venta_id'] ?? null), fn ($query) => $query->where('facturas.punto_venta_id', $filters['punto_venta_id']))
            ->when(filled($filters['user_id'] ?? null), fn ($query) => $query->where('facturas.user_id', $filters['user_id']))
            ->when(filled($filters['estado_factura'] ?? null), fn ($query) => $query->where('facturas.estado_factura', $filters['estado_factura']))
            ->when(filled($filters['codigo_metodo_pago'] ?? null), fn ($query) => $query->where('facturas.codigo_metodo_pago', $filters['codigo_metodo_pago']))
            ->select(
                'factura_detalles.codigo_producto',
                'factura_detalles.descripcion',
                DB::raw('COALESCE(SUM(factura_detalles.cantidad), 0) as cantidad'),
                DB::raw('COALESCE(SUM(factura_detalles.subtotal), 0) as total'),
                DB::raw('COALESCE(AVG(factura_detalles.precio_unitario), 0) as precio_promedio'),
            )
            ->groupBy('factura_detalles.codigo_producto', 'factura_detalles.descripcion')
            ->orderByDesc('total')
            ->limit(30)
            ->get()
            ->map(fn ($row) => [
                'codigo_producto' => (string) $row->codigo_producto,
                'descripcion' => (string) $row->descripcion,
                'cantidad' => round((float) $row->cantidad, 5),
                'total' => round((float) $row->total, 2),
                'precio_promedio' => round((float) $row->precio_promedio, 2),
            ])
            ->values()
            ->all();
    }

    private function facturasRecientes(Builder $query): array
    {
        return $query
            ->latest('fecha_emision')
            ->limit(20)
            ->get()
            ->map(fn (Factura $factura) => [
                'id' => (int) $factura->id,
                'numero_factura' => (int) $factura->numero_factura,
                'fecha_emision' => optional($factura->fecha_emision)?->format('Y-m-d H:i:s'),
                'cliente' => $factura->cliente?->razon_social ?: $factura->cliente?->nombre,
                'usuario' => $factura->user?->name,
                'sucursal' => $factura->sucursal?->nombre,
                'punto_venta' => $factura->puntoVenta?->nombre,
                'estado_factura' => $this->estadoValue($factura->estado_factura),
                'codigo_metodo_pago' => (string) $factura->codigo_metodo_pago,
                'monto_total' => round((float) $factura->monto_total, 2),
            ])
            ->values()
            ->all();
    }

    private function meta(array $filters, ?User $user): array
    {
        return [
            'filtros' => [
                'fecha_desde' => $filters['fecha_desde']->format('Y-m-d'),
                'fecha_hasta' => $filters['fecha_hasta']->format('Y-m-d'),
                'sucursal_id' => $filters['sucursal_id'] ?? null,
                'punto_venta_id' => $filters['punto_venta_id'] ?? null,
                'user_id' => $filters['user_id'] ?? null,
                'estado_factura' => $filters['estado_factura'] ?? null,
                'codigo_metodo_pago' => $filters['codigo_metodo_pago'] ?? null,
            ],
            'sucursales' => OperationalContextScope::sucursalesQuery($user)->get(['id', 'codigo', 'nombre']),
            'puntos_venta' => OperationalContextScope::puntosVentaQuery($user)->get(['id', 'sucursal_id', 'codigo', 'nombre']),
            'usuarios' => User::query()
                ->where('estado', true)
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
            'estados_factura' => array_map(
                fn (FacturaEstadoEnum $estado) => ['value' => $estado->value, 'label' => ucfirst(str_replace('_', ' ', $estado->value))],
                FacturaEstadoEnum::cases(),
            ),
            'metodos_pago' => SinMetodoPago::query()
                ->where('estado', true)
                ->orderBy('codigo_clasificador')
                ->get(['codigo_clasificador', 'descripcion']),
        ];
    }

    private function estadoValue(mixed $estado): string
    {
        if ($estado instanceof FacturaEstadoEnum) {
            return $estado->value;
        }

        return (string) $estado;
    }
}
