# Estado de migracion - FastFact R23

## Identidad

- Nombre completo: FastFact R23
- Slogan: Facturacion rapida, simple y segura.
- Marca asociada: TechDev Servicios R23W7
- Objetivo: sistema de facturacion SIAT para una unica empresa por instalacion.

## Base tecnica

El proyecto nace como copia de `C:\xampp\htdocs\sistema-ventas` para reutilizar lo ya avanzado en Laravel:

- autenticacion;
- usuarios;
- roles y permisos;
- empresa/configuracion;
- sucursales y puntos de venta;
- operadores;
- facturacion SIAT;
- CUIS/CUFD/CAFC;
- eventos significativos;
- sincronizacion SIAT.

## Alcance MVP

Se conserva para primera version:

- Dashboard.
- Configuracion de empresa.
- Parametros generales.
- Sucursales.
- Puntos de venta.
- Operadores.
- Usuarios, roles y permisos.
- Clientes.
- Servicios/productos facturables sin stock.
- Facturas.
- CUIS.
- CUFD.
- CAFC.
- Sincronizacion SIAT.
- Eventos significativos.
- API de integracion para parqueo.

## Fuera del MVP

Se retira de la experiencia principal:

- Inventario.
- Compras.
- Proveedores.
- Cuentas por pagar.
- Kardex.
- Lotes.
- Stock.
- Costos.

## Politica de archivos

Para despliegue no se deben subir:

- `node_modules`;
- `tests`;
- `e2e`;
- `docs/mockups`;
- caches de Laravel;
- logs;
- sesiones locales;
- archivos generados de desarrollo.

El frontend debe compilarse y desplegarse como `public/build`.
## Avance tecnico

- Marca visible ajustada a FastFact R23.
- Menu principal reducido a administracion, facturacion y accesos.
- Migracion aplicada para permitir facturas sin venta y guardar `factura_detalles`.
- Endpoint API creado: `POST /api/facturacion/facturas/emitir-directa`.
- Respuestas de factura incluyen detalles directos cuando estan cargados.
- PDF de factura adaptado para usar `factura_detalles` cuando la factura no tiene venta asociada.
- La factura directa conserva `articulo_id` cuando se emite desde productos internos.

## Pendiente inmediato

- Probar emision directa completa desde la UI con descarga PDF.
- Crear API externa segura para integracion con sistema de parqueo.
- Retirar rutas/API heredadas de compras, kardex, proveedores y stock cuando factura directa quede estable.
