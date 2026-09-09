<?php

namespace App\Http\Controllers\Facturacion;

use App\Http\Controllers\Controller;
use App\Models\Configuracion\Empresa;
use App\Models\Factura;
use App\Services\Facturacion\FacturaService;
use Illuminate\Http\Response;
use Illuminate\View\View;

class FacturaPublicaController extends Controller
{
    public function __construct(private readonly FacturaService $service) {}

    public function show(Factura $factura): View
    {
        $factura->loadMissing(['cliente', 'sucursal', 'puntoVenta']);

        return view('facturas.consulta', [
            'empresa' => Empresa::query()->first(),
            'factura' => $factura,
        ]);
    }

    public function pdf(Factura $factura): Response
    {
        return $this->service->downloadPdf($factura, 'ticket');
    }

    public function xml(Factura $factura): Response
    {
        return $this->service->downloadXml($factura);
    }
}
