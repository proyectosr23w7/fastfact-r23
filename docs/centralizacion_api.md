# API de centralizacion FastFact R23

Esta API esta reservada para monitoreo centralizado de empresas instaladas en subdominios independientes.

## Seguridad

Las rutas requieren:

- Usuario asociado con rol `superadmin`.
- Token Bearer activo.
- Ability explicita del token segun endpoint.

Ningun usuario administrador o cajero puede acceder a estos servicios.

## Endpoints

### Estado general

`GET /api/centralizacion/estado`

Ability requerida:

`centralizacion.estado`

Devuelve:

- Datos basicos de la app y empresa.
- Estado de facturacion.
- Vigencia de token SIAT piloto, produccion y ambiente activo.
- Estado de firma digital y vencimiento del certificado cuando sea verificable.
- Estado CUIS/CUFD por sucursal y punto de venta.
- Resumen operativo de facturas.
- Manifiesto de respaldo recomendado.

### Manifiesto de respaldo

`GET /api/centralizacion/backups/manifest`

Ability requerida:

`centralizacion.backups`

Devuelve:

- Base de datos configurada.
- Tablas detectadas.
- Rutas de archivos que deben respaldarse.
- Exclusiones recomendadas.

## Ejemplo curl

```bash
curl -H "Authorization: Bearer TOKEN" \
  https://empresa.proyectosr23w7.com/api/centralizacion/estado
```

## Nota sobre respaldos

En esta primera etapa el sistema no descarga automaticamente la base de datos ni archivos sensibles. El endpoint deja una via estable para que una app central pueda consultar que debe respaldarse y, en una segunda etapa, agregar generacion/descarga programada de respaldos con control de auditoria.
