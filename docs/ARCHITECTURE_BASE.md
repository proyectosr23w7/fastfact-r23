# Arquitectura Base

FastFact R23 es una aplicacion Laravel 12 + Vue 3/Inertia orientada a facturacion SIAT para Bolivia. En la operacion actual el sistema se encarga de facturacion, clientes, productos facturables, catalogos SIAT, contingencias e integracion API. El modulo de ventas existe en el codigo base, pero no esta activado como flujo operativo de produccion.

## Stack principal

- Backend: Laravel 12, PHP 8.2+, Fortify, Inertia Laravel.
- Frontend: Vue 3, Inertia, TypeScript, Vite, Tailwind CSS, componentes propios basados en Reka UI/lucide.
- Base de datos: MySQL/MariaDB en produccion.
- Autenticacion: sesion web para usuarios internos y Bearer Token para API de integracion.
- Facturacion: servicios SIAT SOAP, generacion XML, firma digital, PDF y QR.

## Backend

```text
app/
|-- Actions/
|   |-- Facturacion/
|   `-- Ventas/
|-- Console/
|   `-- Commands/
|-- Enums/
|-- Helpers/
|-- Http/
|   |-- Controllers/
|   |   |-- Configuracion/
|   |   |-- Facturacion/
|   |   |-- Integracion/
|   |   |-- Inventario/
|   |   |-- Seguridad/
|   |   `-- Ventas/
|   |-- Middleware/
|   |-- Requests/
|   `-- Resources/
|-- Libraries/
|   |-- fpdf/
|   |-- qrlib/
|   `-- xmlseclibs/
|-- Mail/
|-- Models/
|   |-- Configuracion/
|   `-- modelos de dominio principales
|-- Providers/
|-- Repositories/
|   |-- Configuracion/
|   |-- Facturacion/
|   |-- Inventario/
|   |-- Seguridad/
|   `-- Ventas/
|-- Services/
|   |-- Configuracion/
|   |-- Facturacion/
|   |-- Inventario/
|   |-- Seguridad/
|   `-- Ventas/
`-- Traits/
```

## Frontend

```text
resources/js/
|-- components/
|-- composables/
|-- layouts/
|-- pages/
|-- router/
|-- services/
|-- src/
|   |-- components/
|   |-- services/
|   |-- stores/
|   `-- views/
|-- stores/
|-- types/
|-- utils/
`-- views/
```

`resources/js/app.ts` resuelve vistas desde `views/` y conserva `pages/` como compatibilidad con pantallas heredadas de Inertia.

## Modulos operativos actuales

- `Configuracion`: empresa, configuracion general, sucursales, puntos de venta, firma digital, tokens SIAT, modalidad de facturacion e impresion.
- `Seguridad`: usuarios, roles, permisos, contexto operativo de usuario y tokens de integracion.
- `Productos facturables`: articulos/productos, categorias, marcas, unidades de medida, precios y homologacion SIAT.
- `Clientes`: registro y mantenimiento de datos fiscales para emision de facturas.
- `Facturacion`: CUIS, CUFD, CAFC, sincronizacion de catalogos SIAT, facturacion directa, XML/PDF, anulacion, reversion, consulta, reintento y correos.
- `Eventos significativos`: contingencia fuera de linea, contingencia manual con CAFC, recuperacion por paquetes SIAT y validacion de paquetes.
- `Integracion`: API protegida por Bearer Token para productos, clientes, CUIS, CUFD y facturas.
- `Dashboard`: KPIs diarios, facturacion semanal, facturas recientes y estado SIAT.

## Modulos no activados en produccion

- `Ventas`: el codigo contiene servicios, controladores y tablas para ventas internas, stock, lotes, kardex y PDF de venta. Ese flujo no forma parte de la operacion actual del sistema y no debe describirse como proceso activo de produccion.

## Flujo base

```text
Inertia/Vue View
  -> HTTP service/store
  -> Route
  -> Controller
  -> Form Request / Validator
  -> Service
  -> Action(s)
  -> Repository
  -> Model
  -> Resource
  -> JSON/Inertia response
```

Los controladores exponen endpoints y delegan reglas de negocio a servicios. Los servicios orquestan transacciones, validaciones de dominio y acciones. Los repositorios concentran consultas repetidas o procesos de persistencia.

## Rutas principales

- `routes/web.php`: paginas Inertia protegidas por autenticacion, permisos y configuracion de empresa.
- `routes/api.php`: API interna para el frontend y API externa de integracion.
- `routes/settings.php`: perfil, contrasena, apariencia y segundo factor.
- `routes/console.php`: comandos programados o auxiliares cuando correspondan.

## Middleware clave

- `permission`: valida permisos internos de usuarios autenticados.
- `empresa.configurada`: evita operar modulos principales sin datos de empresa.
- `integration.auth`: autentica tokens Bearer de integracion.
- `integration.permission`: valida permisos del token/usuario asociado para endpoints externos.

## Flujos de negocio principales

### Facturacion directa

1. Recibe cliente, sucursal, punto de venta, metodo de pago y detalles facturables.
2. Verifica configuracion SIAT, CUIS vigente y CUFD vigente.
3. Genera CUF/XML/firma, envia a SIAT y persiste detalle propio en `factura_detalles`.
4. Puede usarse desde la interfaz o desde la API de integracion.
5. Envia correo de factura emitida despues del commit cuando corresponde.

### Contingencia SIAT

1. Un usuario autorizado abre un evento significativo por sucursal/punto de venta.
2. Para eventos fuera de linea se usa el CUFD del evento y las facturas quedan pendientes de paquete.
3. Para eventos manuales se exige CAFC vigente y rango autorizado.
4. Al cerrar el evento se registra en SIAT, se generan paquetes de hasta 500 facturas y se envian.
5. El sistema valida paquetes y actualiza facturas a sincronizadas, observadas o pendientes de validacion.

### Integracion externa

1. El cliente externo consume `api/integracion/*` con token Bearer.
2. El token se valida por hash y debe estar vigente/no revocado.
3. Cada endpoint exige permisos de integracion.
4. El contexto operativo puede venir en la solicitud o resolverse desde el usuario asociado al token.

## Tablas principales

- Configuracion: `empresas`, `configuraciones`, `sucursales`, `puntos_venta`.
- Seguridad: `users`, `roles`, `permisos`, `role_user`, `permission_role`, `integration_api_tokens`.
- Productos/clientes: `articulos`, `categorias`, `marcas`, `unidades_medida`, `articulo_precios`, `clientes`.
- SIAT/facturacion: `siat_sincronizaciones`, `cuis`, `cufd`, `cafc`, `facturas`, `factura_detalles`, `factura_anulaciones`, `factura_correlativos`.
- Catalogos SIAT: `sin_actividades`, `sin_productos_servicios`, `sin_motivos_anulacion`, `sin_leyendas`, `sin_documentos_identidad`, `sin_unidades_medida`, `sin_monedas`, `sin_metodos_pago`.
- Contingencia: `eventos_significativos`, `evento_significativo_reportes`, `evento_significativo_paquetes`.
- Tablas no operativas en produccion actual: `venta_cabeceras`, `venta_detalles`, `venta_detalle_lotes`, `articulo_lotes`, `articulo_stocks`, `kardex`.

## Convenciones de implementacion

- Usar `Request` para validacion de endpoints internos cuando exista formulario dedicado.
- Usar validadores locales en controladores de integracion cuando la entrada sea especifica de API externa.
- Mantener operaciones criticas dentro de `DB::transaction`.
- Evitar eliminar registros operativos con historial; preferir cambio de estado cuando el modulo ya lo soporte.
- Usar `Resource` para respuestas JSON consumidas por Vue o integraciones.
- Mantener la configuracion general como registro singleton.
- No versionar credenciales, certificados, `.env`, `vendor`, `node_modules`, builds ni artefactos productivos sensibles.

## Documentacion relacionada

- `docs/DESCRIPCION_SISTEMA.md`: descripcion funcional del sistema.
- `docs/DEPLOYMENT_MULTI_EMPRESA.md`: despliegue por empresa.
- `docs/Guia_Integracion_API_Parqueo_Equipo_Desarrollo_v3.docx`: guia de integracion externa vigente.
