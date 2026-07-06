# Despliegue Multiempresa

Este sistema se despliega como una copia independiente por empresa. Cada copia debe tener su propio subdominio, base de datos, storage, firma digital, token SIAT y usuarios.

## Recomendacion de dominios

Usar subdominio por empresa:

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

Cada empresa debe configurar esos datos en su servidor.

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
2. Registrar configuracion SIAT.
3. Subir firma digital `.p12` o `.pfx`.
4. Registrar contraseña de firma.
5. Verificar CUIS.
6. Generar CUFD.
7. Emitir una factura de prueba oficial solo cuando corresponda.

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
2. CUFD vigente.
3. Descargar PDF/XML de una factura.
4. Consultar factura en SIAT.
5. Probar API con token Bearer.
