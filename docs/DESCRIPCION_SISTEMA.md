# Descripcion Funcional del Sistema

## Resumen

FastFact R23 es un sistema web de facturacion SIAT para Bolivia. Permite configurar una empresa, registrar productos facturables y clientes, emitir facturas electronicas o computarizadas, sincronizar catalogos SIAT, gestionar contingencias y exponer una API de integracion para sistemas externos.

En la operacion actual el sistema no usa el modulo de ventas como flujo productivo. La facturacion se maneja principalmente como facturacion directa desde la interfaz o desde la API de integracion.

El sistema esta preparado para operacion multiempresa mediante copias independientes por empresa. Cada instalacion debe tener su propia base de datos, archivo `.env`, storage, firma digital, token SIAT y usuarios.

## Usuarios y acceso

El acceso interno se realiza con usuario y contrasena. Las pantallas y operaciones se protegen con roles y permisos. El sistema contempla un contexto operativo por usuario para sucursal y punto de venta, usado especialmente en facturacion e integraciones.

Tambien existe acceso por API mediante tokens Bearer. Los tokens se administran desde seguridad y pueden ser revocados. Cada token hereda/verifica permisos para operaciones de integracion.

## Modulo de configuracion

Permite registrar y mantener los datos base de la instalacion:

- Empresa y logo.
- Configuracion general de facturacion.
- Ambiente SIAT piloto o produccion.
- Tipo de facturacion.
- Firma digital y contrasena.
- Tokens SIAT por ambiente.
- Sucursales.
- Puntos de venta.
- Preferencias operativas de productos, precios, codigo de barras, impresion y facturacion.

La configuracion general funciona como registro unico del sistema.

## Modulo de seguridad

Administra:

- Usuarios.
- Roles.
- Permisos.
- Estado de usuarios/roles/permisos.
- Asignacion de roles y permisos.
- Contexto operativo de usuarios.
- Tokens de integracion.

Incluye protecciones para evitar deshabilitar o degradar el usuario superadmin predeterminado.

## Modulo de productos facturables

Administra catalogos y productos facturables:

- Productos/articulos.
- Categorias.
- Marcas.
- Unidades de medida.
- Codigo generico y codigo de barras.
- Alias, tags y atributos de busqueda.
- Precio base y precios por cantidad.
- Homologacion SIAT: actividad economica, producto/servicio SIN y unidad de medida SIAT.
- Campos de apoyo para stock/lotes/kardex presentes en el codigo, aunque el flujo de ventas no esta activo en produccion.

Los productos se usan como base para la facturacion directa desde la interfaz y desde la API.

## Modulo de clientes

Permite registrar y actualizar clientes con:

- Razon social/nombre.
- NIT/CI.
- Tipo de documento de identidad.
- Complemento.
- Telefono.
- Correo.
- Estado.

El sistema valida documentos para evitar datos invalidos al emitir factura.

## Modulo de ventas

El modulo de ventas no esta activado en la operacion actual. Aunque el codigo base contiene controladores, servicios y tablas para ventas internas, no debe considerarse parte del alcance productivo vigente. La documentacion funcional del sistema debe entender la facturacion como flujo principal.

## Modulo de facturacion SIAT

El sistema permite emitir facturas directas sin venta previa. Este es el flujo operativo principal de la instalacion actual.

Operaciones principales:

- Listar y ver facturas.
- Emitir factura directa.
- Reintentar facturas rechazadas u observadas.
- Consultar estado en SIAT.
- Anular factura.
- Revertir anulacion.
- Descargar XML.
- Descargar PDF.
- Enviar correos de factura emitida, anulada y anulacion revertida.

Durante la emision el sistema:

1. Valida configuracion SIAT.
2. Verifica CUIS y CUFD vigentes.
3. Genera numero de factura por sucursal, punto de venta, ambiente y tipo de facturacion.
4. Construye datos de cabecera y detalle.
5. Genera CUF.
6. Genera XML fiscal.
7. Firma XML cuando corresponde.
8. Envia a SIAT.
9. Registra la respuesta.
10. Actualiza estados locales.

## CUIS, CUFD y CAFC

El sistema administra codigos necesarios para operar con SIAT:

- CUIS vigente por sucursal, punto de venta y ambiente.
- CUFD vigente por sucursal, punto de venta y ambiente.
- CAFC para contingencias manuales.

La interfaz y la API pueden consultar o asegurar CUIS/CUFD vigentes segun contexto operativo.

## Sincronizacion de catalogos SIAT

Permite sincronizar y administrar catalogos oficiales:

- Actividades economicas.
- Productos y servicios.
- Motivos de anulacion.
- Leyendas.
- Documentos de identidad.
- Unidades de medida.
- Monedas.
- Metodos de pago.

Ademas permite marcar metodos de pago y unidades de medida como operativos para el uso diario.

## Eventos significativos y contingencia

El sistema soporta eventos significativos SIAT para operar durante fallas o escenarios autorizados:

- Corte de internet.
- Inaccesibilidad al servicio web SIAT.
- Facturacion en lugares sin internet.
- Fallas de software/hardware.
- Corte de energia.
- Otros eventos contemplados por SIAT.

Tipos de contingencia:

- Fuera de linea: emite facturas localmente con CUFD del evento y luego las recupera por paquetes.
- Manual con CAFC: permite transcribir facturas manuales dentro del rango autorizado.

Flujo de recuperacion:

1. Abrir evento.
2. Emitir facturas en contingencia.
3. Cerrar evento.
4. Registrar evento en SIAT.
5. Generar paquetes de facturas.
6. Enviar paquetes.
7. Validar recepcion.
8. Actualizar facturas y evento segun respuesta SIAT.

## API de integracion

La API externa se encuentra bajo `api/integracion/*` y usa Bearer Token.

Operaciones implementadas:

- Productos:
  - Listar.
  - Registrar.
  - Actualizar.
- Clientes:
  - Listar.
  - Registrar.
  - Actualizar.
- CUIS:
  - Consultar vigente.
  - Asegurar vigente.
- CUFD:
  - Consultar vigente.
  - Asegurar vigente.
- Facturas:
  - Emitir factura directa.
  - Anular factura.
  - Revertir anulacion.

Cada operacion exige permisos especificos de integracion.

## Dashboard

El panel principal muestra:

- Facturacion del dia.
- Variacion contra el dia anterior.
- Cantidad de facturas del dia.
- Facturacion de los ultimos 7 dias.
- Facturas recientes.
- Estado SIAT cuando la facturacion esta activa.
- Vigencia de CUFD y ultima sincronizacion.

## Archivos generados

El sistema puede generar:

- XML de factura.
- PDF de factura.
- Paquetes SIAT de contingencia.
- Correos transaccionales relacionados con facturas.

Los certificados, logs, caches, builds, dumps y credenciales no deben versionarse.

## Estado actual

Los modulos principales de configuracion, seguridad, productos facturables, clientes, facturacion SIAT, contingencias e integracion API estan implementados para el alcance actual. El modulo de ventas existe en el codigo, pero no esta activado en produccion ni forma parte del flujo operativo vigente.
