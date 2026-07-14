# Despliegue Multiempresa

FastFact R23 se despliega como una copia independiente por empresa. Cada copia debe tener su propio subdominio, base de datos, storage, firma digital, token SIAT, configuracion y usuarios.

## Recomendacion de dominios

Usar un subdominio por empresa:

```text
facturacion.empresa1.com
facturacion.empresa2.com
r23w7.empresa.com
```

El document root del subdominio debe apuntar a:

```text
/ruta/de/la/copia/public
```

No se recomienda instalarlo como subcarpeta tipo `/FacturacionR23W7`, porque Laravel, Vite, sesiones, URLs firmadas y assets funcionan de forma mas limpia cuando el dominio apunta directo a `public`.

## Archivos que no van en Git

No subir:

```text
.env
.env.backup
.env.production
vendor/
node_modules/
public/build/
storage/backups/
storage/logs/
storage/framework/cache/
storage/framework/sessions/
storage/framework/views/
storage/app/private/siat/certificados/
*.sql
*.p12
*.pfx
```

Cada empresa debe configurar esos datos en su servidor. Tambien deben excluirse dumps productivos, certificados, respaldos, logs, caches, credenciales y archivos temporales de oficina generados al editar documentos.

## Instalacion por empresa

```bash
git clone REPO_URL facturacion-empresa
cd facturacion-empresa

composer install --no-dev --optimize-autoloader
npm ci
npm run build

cp .env.example .env
php artisan key:generate
```

Editar `.env`:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://facturacion.empresa.com
APP_TIMEZONE=America/La_Paz

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=fastfact_empresa
DB_USERNAME=fastfact_empresa
DB_PASSWORD=********

QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database
SESSION_DOMAIN=null
SESSION_PATH=/
```

## Base de datos

Para una empresa nueva:

```bash
php artisan migrate --force
php artisan db:seed --force
```

Para clonar una empresa ya preparada, importar su dump:

```bash
mysql -u USUARIO -p BASE_DATOS < fastfact_empresa_ready_production.sql
```

El dump productivo contiene datos sensibles. No debe guardarse en el repositorio.

## Storage y permisos

```bash
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Permisos sugeridos:

```bash
chmod -R ug+rw storage bootstrap/cache
```

En hosting compartido, ajustar permisos desde el panel si no hay SSH completo.

## Firma digital y SIAT

Por cada empresa:

1. Ingresar al sistema.
2. Registrar empresa y configuracion general.
3. Registrar configuracion SIAT.
4. Subir firma digital `.p12` o `.pfx`.
5. Registrar contrasena de firma.
6. Verificar CUIS.
7. Generar CUFD.
8. Sincronizar catalogos SIAT.
9. Emitir una factura de prueba oficial solo cuando corresponda.

Si la empresa operara contingencias manuales, registrar tambien los CAFC vigentes y revisar rangos autorizados por sucursal, punto de venta y ambiente.

## Workers y tareas

Si el servidor permite procesos permanentes:

```bash
php artisan queue:work --tries=3
```

Cron cada minuto:

```bash
* * * * * cd /ruta/de/la/copia && php artisan schedule:run >> /dev/null 2>&1
```

Si no hay worker permanente, las operaciones principales igual funcionan, pero los procesos diferidos deben revisarse manualmente o configurarse desde el hosting.

## Validacion final

```bash
php artisan about
php artisan route:list
```

Debe quedar:

```text
Environment: production
Debug Mode: OFF
Storage: LINKED
Config: CACHED
Routes: CACHED
```

Pruebas minimas:

1. Login.
2. Acceso al dashboard.
3. Empresa y configuracion SIAT cargadas.
4. CUIS vigente.
5. CUFD vigente.
6. Sincronizacion de catalogos SIAT.
7. Emision de factura de prueba en el ambiente correspondiente.
8. Descargar PDF/XML de una factura.
9. Consultar factura en SIAT.
10. Anular factura de prueba cuando corresponda.
11. Probar API con token Bearer.
12. Verificar envio de correos si la empresa usara notificaciones.

## Operacion posterior al despliegue

- Mantener backup periodico de base de datos y storage.
- Renovar firma digital y tokens SIAT antes de su vencimiento.
- Revisar vigencia diaria de CUFD por sucursal y punto de venta.
- Revisar logs ante facturas observadas o rechazadas.
- Validar permisos de tokens de integracion antes de entregar credenciales a sistemas externos.
- No copiar certificados, `.env` ni dumps entre empresas sin limpieza previa de datos sensibles.
