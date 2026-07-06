# Base de datos y dependencias actuales

## Conexion actual

FastFact R23 ya funciona con una base independiente del sistema original:

- Conexion: `mysql`
- Host: `127.0.0.1`
- Puerto: `3306`
- Base de datos: `fastfact_r23`
- Usuario: `root`

La base heredada `demosvi` queda solo como referencia temporal del sistema base. No debe usarse como base operativa de FastFact R23.

## Estado inicial de la base independiente

Migraciones ejecutadas correctamente en `fastfact_r23`.

Datos semilla actuales:

- Usuarios: 1
- Roles: 2
- Permisos: 12
- Configuraciones: 1
- Empresas: 0
- `factura_detalles`: creada

Usuario inicial de acceso:

- Email: `proyectosr23w7@gmail.com`
- Password: `12345678`

## Datos pendientes de cargar

Se deben cargar o migrar desde `facturacion_computarizada_piloto`:

- datos fiscales de empresa;
- sucursales y puntos de venta;
- parametros SIAT;
- CUIS/CUFD si aplica;
- catalogos SIAT sincronizados;
- clientes;
- servicios/productos facturables sin stock;
- facturas historicas solo si se decide conservarlas.

## Estado de dependencias de facturacion

El modulo de facturacion heredado todavia conserva dependencias con:

- `venta_cabeceras`
- `venta_detalles`
- `articulos`
- relaciones de stock/costo de ventas en algunos flujos relacionados

La emision fiscal reutilizable esta concentrada en:

- `App\Services\Facturacion\FacturaService`
- `App\Services\Facturacion\FacturaDirectaService`
- `App\Actions\Facturacion\GenerarDatosFacturaDesdeVentaAction`
- `App\Actions\Facturacion\GenerarDatosFacturaDirectaAction`
- `App\Actions\Facturacion\GenerarXmlFacturaAction`
- `App\Actions\Facturacion\FirmarXmlFacturaAction`
- `App\Actions\Facturacion\EnviarFacturaSiatAction`
- `App\Actions\Facturacion\GenerarPdfFacturaAction`

## Decision de migracion

No se eliminaran de golpe las tablas o clases de ventas/inventario hasta estabilizar la capa de factura directa. El camino seguro es:

1. Adaptar productos/servicios facturables a modo sin stock.
2. Usar facturas sin `venta_id` mediante `factura_detalles`.
3. Reutilizar acciones SIAT para XML, firmado, envio, PDF y anulacion.
4. Una vez que facturacion directa funcione, retirar rutas/API/clases de compras, kardex, proveedores y stock.

## Riesgo principal

La migracion inicial ya permite `venta_id` nulo en `facturas`, agrega campos de origen/referencia externa y crea `factura_detalles`. El siguiente riesgo esta en adaptar PDF/UI para que usen esos detalles cuando no exista venta asociada.