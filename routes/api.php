<?php

use App\Http\Controllers\Configuracion\ConfiguracionController;
use App\Http\Controllers\Configuracion\EmpresaController;
use App\Http\Controllers\Configuracion\PuntoVentaController;
use App\Http\Controllers\Configuracion\SucursalController;
use App\Http\Controllers\Facturacion\CafcController;
use App\Http\Controllers\Facturacion\CufdController;
use App\Http\Controllers\Facturacion\CuisController;
use App\Http\Controllers\Facturacion\EventoSignificativoController;
use App\Http\Controllers\Facturacion\FacturaController;
use App\Http\Controllers\Facturacion\SiatSyncController;
use App\Http\Controllers\Integracion\IntegrationController;
use App\Http\Controllers\Inventario\ArticuloController;
use App\Http\Controllers\Inventario\ArticuloPrecioController;
use App\Http\Controllers\Inventario\CategoriaController;
use App\Http\Controllers\Inventario\MarcaController;
use App\Http\Controllers\Inventario\UnidadMedidaController;
use App\Http\Controllers\Seguridad\IntegrationApiTokenController;
use App\Http\Controllers\Seguridad\PermisoController as SeguridadPermisoController;
use App\Http\Controllers\Seguridad\RolController as SeguridadRolController;
use App\Http\Controllers\Seguridad\UsuarioController as SeguridadUsuarioController;
use App\Http\Controllers\Ventas\ClienteController as VentaClienteController;
use Illuminate\Support\Facades\Route;

Route::middleware(['integration.auth'])->prefix('integracion')->group(function () {
    Route::get('productos', [IntegrationController::class, 'productos'])
        ->middleware('integration.permission:integracion.productos.manage');
    Route::post('productos', [IntegrationController::class, 'registrarProducto'])
        ->middleware('integration.permission:integracion.productos.manage');
    Route::put('productos/{articulo}', [IntegrationController::class, 'actualizarProducto'])
        ->middleware('integration.permission:integracion.productos.manage');
    Route::patch('productos/{articulo}', [IntegrationController::class, 'actualizarProducto'])
        ->middleware('integration.permission:integracion.productos.manage');

    Route::get('clientes', [IntegrationController::class, 'clientes'])
        ->middleware('integration.permission:integracion.clientes.manage');
    Route::post('clientes', [IntegrationController::class, 'registrarCliente'])
        ->middleware('integration.permission:integracion.clientes.manage');
    Route::put('clientes/{cliente}', [IntegrationController::class, 'actualizarCliente'])
        ->middleware('integration.permission:integracion.clientes.manage');
    Route::patch('clientes/{cliente}', [IntegrationController::class, 'actualizarCliente'])
        ->middleware('integration.permission:integracion.clientes.manage');

    Route::get('cuis/actual', [IntegrationController::class, 'cuisActual'])
        ->middleware('integration.permission:integracion.cuis.manage');
    Route::post('cuis/asegurar', [IntegrationController::class, 'asegurarCuis'])
        ->middleware('integration.permission:integracion.cuis.manage');

    Route::get('cufd/actual', [IntegrationController::class, 'cufdActual'])
        ->middleware('integration.permission:integracion.cufd.manage');
    Route::post('cufd/asegurar', [IntegrationController::class, 'asegurarCufd'])
        ->middleware('integration.permission:integracion.cufd.manage');

    Route::post('facturas', [IntegrationController::class, 'emitirFactura'])
        ->middleware('integration.permission:integracion.facturas.emitir');
    Route::patch('facturas/{factura}/anular', [IntegrationController::class, 'anularFactura'])
        ->middleware('integration.permission:integracion.facturas.anular');
    Route::patch('facturas/{factura}/revertir-anulacion', [IntegrationController::class, 'revertirFactura'])
        ->middleware('integration.permission:integracion.facturas.revertir');
});

Route::middleware(['web', 'auth'])->prefix('configuracion')->group(function () {
    Route::get('empresa/{empresa}/logo', [EmpresaController::class, 'logo'])
        ->name('configuracion.empresa.logo');
    Route::apiResource('empresa', EmpresaController::class)
        ->middleware('permission:configuracion.empresa.view,configuracion.general.manage');
    Route::apiResource('configuracion', ConfiguracionController::class)
        ->middleware('permission:configuracion.general.manage');
    Route::apiResource('sucursales', SucursalController::class)
        ->middleware('permission:configuracion.sucursales.manage')
        ->parameters(['sucursales' => 'sucursal']);
    Route::apiResource('puntos-venta', PuntoVentaController::class)
        ->middleware('permission:configuracion.puntos_venta.manage')
        ->parameters(['puntos-venta' => 'punto_venta']);
});

Route::middleware(['web', 'auth'])->prefix('seguridad')->group(function () {
    Route::get('contexto', [SeguridadUsuarioController::class, 'contexto']);

    Route::get('tokens-integracion', [IntegrationApiTokenController::class, 'index'])
        ->middleware('permission:integracion.tokens.manage,seguridad.usuarios.manage');
    Route::post('tokens-integracion', [IntegrationApiTokenController::class, 'store'])
        ->middleware('permission:integracion.tokens.manage,seguridad.usuarios.manage');
    Route::patch('tokens-integracion/{token}/revocar', [IntegrationApiTokenController::class, 'revoke'])
        ->middleware('permission:integracion.tokens.manage,seguridad.usuarios.manage');

    Route::apiResource('usuarios', SeguridadUsuarioController::class)
        ->middleware('permission:seguridad.usuarios.manage')
        ->except('destroy')
        ->parameters(['usuarios' => 'usuario']);
    Route::patch('usuarios/{usuario}/estado', [SeguridadUsuarioController::class, 'updateEstado'])->middleware('permission:seguridad.usuarios.manage');
    Route::patch('usuarios/{usuario}/roles', [SeguridadUsuarioController::class, 'assignRoles'])->middleware('permission:seguridad.usuarios.manage');
    Route::patch('usuarios/{usuario}/acceso', [SeguridadUsuarioController::class, 'assignAccess'])->middleware('permission:seguridad.usuarios.manage');

    Route::apiResource('roles', SeguridadRolController::class)
        ->middleware('permission:seguridad.roles.manage')
        ->except('destroy')
        ->parameters(['roles' => 'rol']);
    Route::patch('roles/{rol}/estado', [SeguridadRolController::class, 'updateEstado'])->middleware('permission:seguridad.roles.manage');
    Route::patch('roles/{rol}/permisos', [SeguridadRolController::class, 'assignPermissions'])->middleware('permission:seguridad.roles.manage');

    Route::apiResource('permisos', SeguridadPermisoController::class)
        ->middleware('permission:seguridad.permisos.manage')
        ->except('destroy')
        ->parameters(['permisos' => 'permiso']);
    Route::patch('permisos/{permiso}/estado', [SeguridadPermisoController::class, 'updateEstado'])->middleware('permission:seguridad.permisos.manage');
});

Route::middleware(['web', 'auth'])->prefix('inventario')->group(function () {
    Route::apiResource('articulos', ArticuloController::class)
        ->middleware('permission:facturacion.productos.manage')
        ->parameters(['articulos' => 'articulo']);
    Route::patch('articulos/{articulo}/estado', [ArticuloController::class, 'updateEstado'])->middleware('permission:facturacion.productos.manage');

    Route::apiResource('categorias', CategoriaController::class)
        ->middleware('permission:facturacion.productos.manage')
        ->parameters(['categorias' => 'categoria']);
    Route::patch('categorias/{categoria}/estado', [CategoriaController::class, 'updateEstado'])->middleware('permission:facturacion.productos.manage');

    Route::apiResource('marcas', MarcaController::class)
        ->middleware('permission:facturacion.productos.manage')
        ->parameters(['marcas' => 'marca']);
    Route::patch('marcas/{marca}/estado', [MarcaController::class, 'updateEstado'])->middleware('permission:facturacion.productos.manage');

    Route::apiResource('unidades-medida', UnidadMedidaController::class)
        ->middleware('permission:facturacion.catalogos.manage')
        ->parameters(['unidades-medida' => 'unidad_medida']);
    Route::patch('unidades-medida/{unidad_medida}/estado', [UnidadMedidaController::class, 'updateEstado'])->middleware('permission:facturacion.catalogos.manage');

    Route::apiResource('articulo-precios', ArticuloPrecioController::class)
        ->middleware('permission:facturacion.productos.manage')
        ->parameters(['articulo-precios' => 'articulo_precio']);
    Route::patch('articulo-precios/{articulo_precio}/estado', [ArticuloPrecioController::class, 'updateEstado'])->middleware('permission:facturacion.productos.manage');
});

Route::middleware(['web', 'auth'])->prefix('ventas')->group(function () {
    Route::get('clientes', [VentaClienteController::class, 'index'])->middleware('permission:ventas.clientes.view,ventas.clientes.manage');
    Route::post('clientes', [VentaClienteController::class, 'store'])->middleware('permission:ventas.clientes.create,ventas.clientes.manage');
    Route::get('clientes/{cliente}', [VentaClienteController::class, 'show'])->middleware('permission:ventas.clientes.view,ventas.clientes.manage');
    Route::put('clientes/{cliente}', [VentaClienteController::class, 'update'])->middleware('permission:ventas.clientes.edit,ventas.clientes.manage');
    Route::patch('clientes/{cliente}', [VentaClienteController::class, 'update'])->middleware('permission:ventas.clientes.edit,ventas.clientes.manage');
    Route::patch('clientes/{cliente}/estado', [VentaClienteController::class, 'updateEstado'])->middleware('permission:ventas.clientes.edit,ventas.clientes.manage');
});

Route::middleware(['web', 'auth'])->prefix('facturacion')->group(function () {
    Route::get('sincronizaciones/catalogos', [SiatSyncController::class, 'index'])->middleware('permission:facturacion.siat.sync,facturacion.catalogos.manage');
    Route::post('sincronizaciones/catalogos', [SiatSyncController::class, 'sincronizar'])->middleware('permission:facturacion.siat.sync');
    Route::patch('catalogos/metodos-pago/{metodo_pago}/estado', [SiatSyncController::class, 'updateMetodoPagoEstado'])->middleware('permission:facturacion.catalogos.manage');
    Route::patch('catalogos/metodos-pago/{metodo_pago}/operativo', [SiatSyncController::class, 'updateMetodoPagoOperativo'])->middleware('permission:facturacion.catalogos.manage');
    Route::patch('catalogos/unidades-medida/{unidad_medida}/estado', [SiatSyncController::class, 'updateUnidadMedidaEstado'])->middleware('permission:facturacion.catalogos.manage');

    Route::get('cuis', [CuisController::class, 'index'])->middleware('permission:facturacion.siat.sync');
    Route::post('cuis', [CuisController::class, 'store'])->middleware('permission:facturacion.siat.sync');

    Route::get('cufd', [CufdController::class, 'index'])->middleware('permission:facturacion.siat.sync');
    Route::post('cufd', [CufdController::class, 'store'])->middleware('permission:facturacion.siat.sync');

    Route::get('cafc', [CafcController::class, 'index'])->middleware('permission:facturacion.siat.sync');
    Route::post('cafc', [CafcController::class, 'store'])->middleware('permission:facturacion.siat.sync');
    Route::patch('cafc/{cafc}', [CafcController::class, 'update'])->middleware('permission:facturacion.siat.sync');
    Route::patch('cafc/{cafc}/estado', [CafcController::class, 'updateEstado'])->middleware('permission:facturacion.siat.sync');

    Route::get('facturas', [FacturaController::class, 'index'])->middleware('permission:facturacion.facturas.view,facturacion.facturas.emitir');
    Route::get('facturas/{factura}', [FacturaController::class, 'show'])->middleware('permission:facturacion.facturas.view,facturacion.facturas.emitir');
    Route::post('facturas/emitir-directa', [FacturaController::class, 'emitirDirecta'])->middleware('permission:facturacion.facturas.emitir');
    Route::post('facturas/{factura}/reintentar', [FacturaController::class, 'reintentar'])->middleware('permission:facturacion.siat.sync');
    Route::patch('facturas/{factura}/anular', [FacturaController::class, 'anular'])->middleware('permission:facturacion.siat.sync');
    Route::patch('facturas/{factura}/revertir-anulacion', [FacturaController::class, 'revertirAnulacion'])->middleware('permission:facturacion.facturas.revertir_anulacion');
    Route::post('facturas/{factura}/reenviar-correo', [FacturaController::class, 'reenviarCorreo'])->middleware('permission:facturacion.facturas.view,facturacion.facturas.emitir');
    Route::post('facturas/{factura}/consultar', [FacturaController::class, 'consultar'])->middleware('permission:facturacion.siat.sync');
    Route::get('facturas/{factura}/descargar/xml', [FacturaController::class, 'downloadXml'])->middleware('permission:facturacion.facturas.view,facturacion.facturas.emitir');
    Route::get('facturas/{factura}/descargar/pdf', [FacturaController::class, 'downloadPdf'])->middleware('permission:facturacion.facturas.view,facturacion.facturas.emitir');

    Route::get('eventos-significativos', [EventoSignificativoController::class, 'index'])->middleware('permission:facturacion.siat.sync');
    Route::post('eventos-significativos', [EventoSignificativoController::class, 'store'])->middleware('permission:facturacion.siat.sync');
    Route::post('eventos-significativos/reportes', [EventoSignificativoController::class, 'report'])->middleware('permission:facturacion.siat.sync');
    Route::patch('eventos-significativos/{evento}/cerrar', [EventoSignificativoController::class, 'close'])->middleware('permission:facturacion.siat.sync');
    Route::post('eventos-significativos/{evento}/procesar-recuperacion', [EventoSignificativoController::class, 'processRecovery'])->middleware('permission:facturacion.siat.sync');
});
