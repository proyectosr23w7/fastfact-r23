<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
})->name('home');

Route::get('dashboard', DashboardController::class)
    ->middleware(['auth', 'verified', 'permission:sistema.access', 'empresa.configurada'])
    ->name('dashboard');

Route::middleware(['auth', 'permission:sistema.access'])->group(function () {
    Route::get('configuracion/empresa', fn () => Inertia::render('configuracion/EmpresaView'))
        ->middleware('permission:configuracion.empresa.view')
        ->name('configuracion.empresa.page');
});

Route::middleware(['auth', 'permission:sistema.access', 'empresa.configurada'])->group(function () {
    Route::get('configuracion/configuracion', fn () => Inertia::render('configuracion/ConfiguracionView'))
        ->middleware('permission:configuracion.general.manage')
        ->name('configuracion.configuracion.page');
    Route::get('configuracion/sucursales', fn () => Inertia::render('configuracion/SucursalView'))
        ->middleware('permission:configuracion.sucursales.manage')
        ->name('configuracion.sucursales.page');
    Route::get('configuracion/puntos-venta', fn () => Inertia::render('configuracion/PuntoVentaView'))
        ->middleware('permission:configuracion.puntos_venta.manage')
        ->name('configuracion.puntos-venta.page');
    Route::get('seguridad/usuarios', fn () => Inertia::render('seguridad/UsuarioView'))
        ->middleware('permission:seguridad.usuarios.manage')
        ->name('seguridad.usuarios.page');
    Route::get('seguridad/roles', fn () => Inertia::render('seguridad/RolView'))
        ->middleware('permission:seguridad.roles.manage')
        ->name('seguridad.roles.page');
    Route::get('seguridad/permisos', fn () => Inertia::render('seguridad/PermisoView'))
        ->middleware('permission:seguridad.permisos.manage')
        ->name('seguridad.permisos.page');
    Route::get('seguridad/tokens-integracion', fn () => Inertia::render('seguridad/IntegrationTokenView'))
        ->middleware('permission:integracion.tokens.manage,seguridad.usuarios.manage')
        ->name('seguridad.tokens-integracion.page');

    Route::get('facturacion/facturas', fn () => Inertia::render('facturacion/FacturaView'))
        ->middleware('permission:facturacion.facturas.view,facturacion.facturas.emitir')
        ->name('facturacion.facturas.page');
    Route::get('facturacion/facturas/nueva', fn () => Inertia::render('facturacion/FacturaDirectaView'))
        ->middleware('permission:facturacion.facturas.emitir')
        ->name('facturacion.facturas.nueva.page');
    Route::get('facturacion/productos', fn () => Inertia::render('inventario/ArticuloView'))
        ->middleware('permission:facturacion.productos.manage')
        ->name('facturacion.productos.page');
    Route::get('facturacion/clientes', fn () => Inertia::render('facturacion/ClienteView'))
        ->middleware('permission:ventas.clientes.manage')
        ->name('facturacion.clientes.page');
    Route::get('facturacion/cuis', fn () => Inertia::render('facturacion/CuisView'))
        ->middleware('permission:facturacion.siat.sync')
        ->name('facturacion.cuis.page');
    Route::get('facturacion/cufd', fn () => Inertia::render('facturacion/CufdView'))
        ->middleware('permission:facturacion.siat.sync')
        ->name('facturacion.cufd.page');
    Route::get('facturacion/cafc', fn () => Inertia::render('facturacion/CafcView'))
        ->middleware('permission:facturacion.siat.sync')
        ->name('facturacion.cafc.page');
    Route::get('facturacion/sincronizaciones-siat', fn () => Inertia::render('facturacion/SincronizacionSiatView'))
        ->middleware('permission:facturacion.siat.sync')
        ->name('facturacion.sincronizaciones.page');
    Route::get('facturacion/catalogos-siat', fn () => Inertia::render('facturacion/SincronizacionSiatView', ['catalogOnly' => true]))
        ->middleware('permission:facturacion.catalogos.manage')
        ->name('facturacion.catalogos.page');
    Route::get('facturacion/eventos-significativos', fn () => Inertia::render('facturacion/EventoSignificativoView'))
        ->middleware('permission:facturacion.siat.sync')
        ->name('facturacion.eventos.page');
});

require __DIR__.'/settings.php';
