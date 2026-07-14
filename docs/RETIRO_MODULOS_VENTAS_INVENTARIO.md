# Retiro de ventas e inventario físico

## Alcance

Esta limpieza retira de la base reutilizable el flujo de ventas, el control de stock por sucursal, lotes y kardex. Se conservan clientes, artículos/productos facturables, categorías, marcas, unidades de medida, precios y facturación directa SIAT.

La ruta `POST /api/facturacion/facturas/emitir`, que dependía de una venta interna, fue retirada. La emisión base continúa mediante `POST /api/facturacion/facturas/emitir-directa` y `POST /api/integracion/facturas`.

## Archivos que deben desaparecer en instalaciones existentes

Cuando el despliegue no elimina archivos ausentes en la nueva versión, borrar expresamente:

```text
app/Actions/Ventas/CalcularCostoSalidaAction.php
app/Actions/Ventas/CalcularUtilidadVentaAction.php
app/Actions/Ventas/ConsumirLotesVentaAction.php
app/Actions/Ventas/DescontarStockArticuloAction.php
app/Actions/Ventas/GenerarPdfVentaAction.php
app/Actions/Ventas/RegistrarKardexVentaAction.php
app/Actions/Ventas/RestituirLotesVentaAction.php
app/Actions/Ventas/RevertirVentaAction.php
app/Enums/ReferenciaKardexEnum.php
app/Enums/TipoDocumentoVentaEnum.php
app/Enums/TipoMovimientoKardexEnum.php
app/Enums/VentaEstadoEnum.php
app/Http/Controllers/Ventas/VentaController.php
app/Http/Requests/Facturacion/EmitirFacturaRequest.php
app/Http/Requests/Venta/AnularVentaRequest.php
app/Http/Requests/Venta/ConfirmVentaRequest.php
app/Http/Requests/Venta/StoreVentaRequest.php
app/Http/Requests/Venta/UpdateVentaRequest.php
app/Http/Resources/ArticuloLoteResource.php
app/Http/Resources/KardexResource.php
app/Http/Resources/VentaCabeceraResource.php
app/Http/Resources/VentaDetalleLoteResource.php
app/Http/Resources/VentaDetalleResource.php
app/Repositories/Inventario/ArticuloLoteRepository.php
app/Repositories/Inventario/KardexRepository.php
app/Repositories/Ventas/VentaCabeceraRepository.php
app/Repositories/Ventas/VentaDetalleLoteRepository.php
app/Services/Inventario/ArticuloLoteService.php
app/Services/Inventario/KardexService.php
app/Services/Ventas/VentaDetalleLoteService.php
app/Services/Ventas/VentaService.php
resources/js/actions/App/Http/Controllers/Ventas/VentaController.ts
```

No borrar `app/Http/Controllers/Ventas/ClienteController.php`, `app/Services/Ventas/ClienteService.php`, `app/Repositories/Ventas/ClienteRepository.php` ni los componentes `resources/js/src/components/ventas/*`: pese a su nombre histórico, actualmente implementan clientes y el formulario reutilizado por facturación directa. Deben renombrarse en una refactorización separada para no arriesgar el flujo SIAT.

## Tablas retiradas por migración

La migración `2026_07_13_000001_remove_sales_and_physical_inventory_modules.php` elimina:

```text
venta_detalle_lotes
venta_detalles
venta_cabeceras
articulo_stocks
articulo_lotes
kardex
```

También elimina la columna y clave foránea `facturas.venta_id` y el permiso operativo `ventas.access`. Los permisos históricos `ventas.clientes.*` se conservan porque todavía protegen el catálogo común de clientes. Las facturas, sus detalles fiscales, XML, PDF, clientes y productos se conservan.

## Procedimiento de despliegue

1. Poner temporalmente la aplicación en mantenimiento.
2. Respaldar la base de datos completa y los directorios de XML/PDF.
3. Si alguna instalación todavía usa ventas, no aplicar esta limpieza hasta exportar o migrar sus datos.
4. Publicar el código nuevo usando un método que elimine archivos obsoletos. Si se copian archivos de forma incremental, usar la lista anterior.
5. Ejecutar `composer install --no-dev --optimize-autoloader`.
6. Ejecutar `php artisan migrate --force`.
7. Generar y publicar el frontend con `npm ci` y `npm run build`, o transferir el `public/build` generado por el pipeline.
8. Ejecutar `php artisan optimize:clear` y luego `php artisan optimize`.
9. Sacar la aplicación de mantenimiento.
10. Verificar inicio de sesión, clientes, productos, factura directa, descarga XML/PDF, anulación, contingencia e integración API.

## Advertencias

- La migración es irreversible porque elimina datos operativos. Una reversión requiere restaurar el respaldo.
- No eliminar las migraciones históricas que crearon estas tablas. Las instalaciones nuevas deben ejecutar primero la historia del esquema y finalmente la migración de limpieza.
- No ejecutar sentencias `DROP TABLE` manuales; la migración controla el orden de claves foráneas.
- Los proyectos destinados específicamente a ventas e inventario no deben recibir esta migración.
