# Despliegue y migracion de Tentaciones

Esta guia corresponde al dump `proyect2_facturacion_tentaciones (1).sql` generado el
22 de septiembre de 2026. La migracion fue ensayada contra una base nueva antes de
preparar este procedimiento.

## Alcance validado

El importador traslada:

- empresa y configuracion SIAT;
- sucursales y puntos de venta;
- usuarios operativos, con contrasenas temporales nuevas;
- catalogos SIN;
- clientes;
- productos y precios;
- CUIS y CUFD historicos;
- facturas y detalles historicos, incluido su XML fiscal.

No se deben copiar las claves de usuarios del sistema anterior. Tampoco se deben
reutilizar rutas de PDF del servidor anterior. La ruta original queda solo en los
metadatos de cada factura.

## Resultado esperado del dump revisado

| Entidad | Origen | Destino esperado |
| --- | ---: | ---: |
| Clientes | 101 | 101 con codigo `LEG-*` |
| Productos | 697 | 697 |
| Facturas | 226 | 226 con origen `legacy` |
| Detalles de factura | n/a | 604 |
| Sucursales | 2 | 2 |
| Puntos de venta | 2 | 2 |
| Usuarios legacy (sin TechDevAdmin) | 3 | 3 |
| Total facturado historico | 758956.78 | 758956.78 |

Hay dos productos legacy con codigo `7`. El segundo se conserva con un codigo
tecnico sufijado `-LEG-{id}`. Tambien hay siete detalles historicos cuyos seis
codigos de producto ya no existen en el catalogo legacy. Se conservan completos,
pero con `articulo_id` nulo.

Los 233 CUFD del dump ya estan vencidos. Se conservan como historial, pero antes
de emitir una factura nueva se debe obtener un CUFD vigente. Los CUIS del dump
declaran vigencia hasta agosto de 2027; aun asi deben validarse contra SIAT en el
servidor definitivo.

## 1. Preparar el servidor

El subdominio debe apuntar a la carpeta `public` de Laravel. Instalar PHP, las
extensiones requeridas por Composer, MariaDB/MySQL y un certificado HTTPS valido.

```bash
git clone REPO_URL fastfact-tentaciones
cd fastfact-tentaciones
composer install --no-dev --optimize-autoloader
npm ci
npm run build
cp .env.example .env
php artisan key:generate
```

Configurar `.env` con `APP_ENV=production`, `APP_DEBUG=false`, la URL HTTPS y una
base vacia exclusiva. No guardar `.env`, el dump, certificados ni tokens en Git.

## 2. Crear respaldos y ventana de corte

1. Hacer un ensayo completo en staging con una copia reciente del dump.
2. Definir una ventana sin nuevas facturas en el sistema anterior.
3. Al iniciar el corte, poner el sistema anterior en modo solo lectura.
4. Generar un dump final y calcular su hash SHA-256.
5. Respaldar la base destino y `storage` antes de cualquier cambio.

Ejemplo de respaldo:

```bash
mysqldump --single-transaction --routines --triggers -u USUARIO -p BASE_DESTINO > fastfact_pre_migracion.sql
```

## 3. Crear el esquema nuevo

```bash
php artisan down --secret="URL-SECRETA-DE-MANTENIMIENTO"
php artisan migrate --force
```

No usar `migrate:fresh` en una base que contenga datos.

## 4. Cargar el dump en una base temporal

Crear una base temporal separada; nunca importar el dump legacy directamente
sobre la base de FastFact.

```bash
mysql -u USUARIO -p -e "CREATE DATABASE tentaciones_legacy_import CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u USUARIO -p tentaciones_legacy_import < proyect2_facturacion_tentaciones.sql
```

El usuario configurado en `.env` debe tener acceso temporal de lectura a esa base.

## 5. Ejecutar la migracion

Elegir contrasenas temporales fuertes y transmitirlas por un canal seguro. No usar
los valores predeterminados del comando.

```bash
php artisan fastfact:import-legacy-production \
  --database=tentaciones_legacy_import \
  --ambiente=produccion \
  --tipo-facturacion=electronica \
  --admin-email=ADMIN_REAL \
  --admin-password='CLAVE_TEMPORAL_FUERTE' \
  --legacy-user-domain=tentaciones.local \
  --legacy-user-password='OTRA_CLAVE_TEMPORAL_FUERTE' \
  --no-interaction
```

El comando usa una transaccion: si falla, no se debe continuar con el despliegue.
Corregir la causa y restaurar una base destino limpia antes de reintentarlo. El
comando no debe ejecutarse dos veces sobre una importacion ya completada.

## 6. Conciliar los datos

Ejecutar en la base destino:

```sql
SELECT COUNT(*) FROM clientes WHERE codigo LIKE 'LEG-%';
SELECT COUNT(*) FROM articulos;
SELECT COUNT(*) FROM facturas WHERE origen = 'legacy';
SELECT COUNT(*) FROM factura_detalles;
SELECT ROUND(SUM(monto_total), 2) FROM facturas WHERE origen = 'legacy';
SELECT COUNT(*) FROM facturas
 WHERE origen = 'legacy' AND (cliente_id IS NULL OR punto_venta_id IS NULL);
SELECT COUNT(*) FROM (
  SELECT cuf FROM facturas WHERE origen = 'legacy'
  GROUP BY cuf HAVING COUNT(*) > 1
) duplicados;
```

Los resultados esperados son `101`, `697`, `226`, `604`, `758956.78`, `0` y
`0`, respectivamente.

Verificar ademas una muestra de facturas anuladas y emitidas comparando numero,
CUF, fecha, cliente, total, XML y detalle con el sistema anterior.

## 7. Activar SIAT y la aplicacion

1. Instalar la firma digital en el storage privado y configurar su contrasena.
2. Confirmar token, codigo de sistema, NIT, modalidad y ambiente.
3. Validar o renovar CUIS para cada punto de venta.
4. Obtener un CUFD nuevo; no usar los CUFD historicos vencidos.
5. Sincronizar catalogos SIAT.
6. Probar login, permisos, PDF/XML y consulta de facturas historicas.
7. Emitir primero una factura controlada conforme al procedimiento fiscal de la empresa.

```bash
php artisan storage:link
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan up
```

Configurar tambien `schedule:run` cada minuto y `queue:work` si el hosting permite
un worker permanente.

## 8. Reversion

Si falla cualquier conciliacion o prueba SIAT:

1. mantener FastFact en mantenimiento;
2. reabrir el sistema anterior para la operacion;
3. restaurar la base destino desde `fastfact_pre_migracion.sql`;
4. restaurar `storage` si fue modificado;
5. documentar la causa y repetir primero en staging.

No emitir alternativamente desde ambos sistemas con el mismo punto de venta durante
el corte: se debe evitar cualquier riesgo de correlativos o estados fiscales divergentes.

## 9. Limpieza posterior

Despues de la aceptacion y de conservar un respaldo cifrado:

- revocar el acceso de FastFact a `tentaciones_legacy_import`;
- eliminar la base temporal;
- retirar el dump del servidor web;
- forzar cambio de contrasena a todos los usuarios migrados;
- programar respaldos de base de datos y `storage` y probar su restauracion.

