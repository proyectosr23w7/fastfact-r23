<?php

use App\Actions\Facturacion\GenerarPdfFacturaAction;
use App\Enums\TipoImpresionEnum;
use App\Helpers\BoliviaPdfHelper;
use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\PuntoVenta;
use App\Models\Configuracion\Sucursal;
use App\Models\Factura;
use App\Models\FacturaDetalle;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

uses(Tests\TestCase::class);

function facturaParaPruebaRollo(int $count): Factura
{
    $factura = new Factura([
        'numero_factura' => 123,
        'cuf' => str_repeat('ABC123', 10),
        'fecha_emision' => '2026-11-02 10:30:00',
        'ambiente_facturacion' => 'piloto',
        'codigo_emision' => 2,
        'cliente_id' => 1,
        'monto_total' => 190 * $count,
        'monto_sujeto_iva' => 190 * $count - 10,
        'monto_gift_card' => 10,
        'descuento_global' => 0,
        'leyenda' => 'Ley N 453: El proveedor debe brindar atencion sin discriminacion, con respeto, calidez y cordialidad.',
    ]);
    $factura->setRelation('sucursal', new Sucursal(['nombre' => 'CASA MATRIZ', 'municipio' => 'La Paz', 'direccion' => 'Avenida de prueba 123']));
    $factura->setRelation('puntoVenta', new PuntoVenta(['codigo' => 0, 'tipo_impresion' => 'rollo']));
    $factura->setRelation('cliente', new class extends Model
    {
        protected $guarded = [];

        public function __construct()
        {
            parent::__construct(['nombre' => 'CLIENTE DE PRUEBA CON NOMBRE LARGO PARA COMPROBAR SALTOS DE LINEA', 'nit_ci' => '1234567']);
        }
    });
    $items = new Collection;
    for ($i = 1; $i <= $count; $i++) {
        $detalle = new FacturaDetalle([
            'codigo_producto' => 'P-'.$i,
            'descripcion' => 'Producto de prueba '.$i.' con descripcion extensa para verificar que el detalle no se corte al imprimir en rollo.',
            'cantidad' => 2,
            'unidad_medida' => 57,
            'precio_unitario' => 100,
            'monto_descuento' => 10,
            'subtotal' => 190,
        ]);
        $detalle->setRelation('articulo', null);
        $items->push($detalle);
    }
    $factura->setRelation('detalles', $items);

    return $factura;
}

test('rollo is an independent supported format', function () {
    expect(TipoImpresionEnum::from('rollo'))->toBe(TipoImpresionEnum::ROLLO);
    expect(TipoImpresionEnum::from('ticket'))->toBe(TipoImpresionEnum::TICKET);
});

test('detailed roll grows with the invoice without additional pages', function () {
    BoliviaPdfHelper::bootLibraries();
    $method = new ReflectionMethod(GenerarPdfFacturaAction::class, 'buildRollo');
    $empresa = new Empresa(['razon_social' => 'EMPRESA DE PRUEBA', 'nit' => '123456789']);
    $short = $method->invoke(new GenerarPdfFacturaAction, facturaParaPruebaRollo(1), $empresa);
    $long = $method->invoke(new GenerarPdfFacturaAction, facturaParaPruebaRollo(30), $empresa);

    expect($short->GetPageWidth())->toEqualWithDelta(80, 0.01);
    expect($long->GetPageWidth())->toEqualWithDelta(80, 0.01);
    expect($long->GetPageHeight())->toBeGreaterThan($short->GetPageHeight());
    expect($long->PageNo())->toBe(1);
    expect($long->GetY())->toBeLessThan($long->GetPageHeight() - 4);
    expect(BoliviaPdfHelper::output($long))->toStartWith('%PDF-');
});

test('summary ticket retains its existing square size', function () {
    BoliviaPdfHelper::bootLibraries();
    $pdf = (new ReflectionMethod(GenerarPdfFacturaAction::class, 'buildTicket'))
        ->invoke(new GenerarPdfFacturaAction, facturaParaPruebaRollo(1), null);

    expect($pdf->GetPageWidth())->toEqualWithDelta(85, 0.01);
    expect($pdf->GetPageHeight())->toEqualWithDelta(85, 0.01);
});
