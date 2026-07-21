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

### Listar backups

`GET /api/centralizacion/backups`

Ability requerida:

`centralizacion.backups`

Devuelve los ZIP generados localmente en el disco `local`, carpeta `backups`.

### Generar backup

`POST /api/centralizacion/backups`

Ability requerida:

`centralizacion.backups`

Genera un archivo ZIP con:

- `database.sql`
- `manifest.json`
- `storage/app/siat`
- `storage/app/private`
- `storage/app/public`

### Descargar backup

`GET /api/centralizacion/backups/{filename}`

Ability requerida:

`centralizacion.backups`

Descarga un ZIP generado previamente. El nombre de archivo se valida con `basename` para evitar lectura fuera de la carpeta de backups.

## Ejemplo curl

```bash
curl -H "Authorization: Bearer TOKEN" \
  https://empresa.proyectosr23w7.com/api/centralizacion/estado
```

```bash
curl -X POST -H "Authorization: Bearer TOKEN" \
  https://empresa.proyectosr23w7.com/api/centralizacion/backups
```

## Nota sobre respaldos

La generacion de backup no depende de consola del servidor: el SQL se produce desde Laravel/PDO y el ZIP se genera con la extension `ZipArchive` de PHP. El archivo contiene informacion sensible y solo debe ser consumido por superadministradores o por una app central autorizada.
