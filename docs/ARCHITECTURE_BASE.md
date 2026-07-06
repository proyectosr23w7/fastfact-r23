# Arquitectura Base

## Backend

```text
app/
|-- Actions/
|-- Enums/
|-- Helpers/
|-- Http/
|   |-- Controllers/
|   |   |-- Auth/
|   |   |-- Configuracion/
|   |   |-- Inventario/
|   |   |-- Compras/
|   |   |-- Ventas/
|   |   |-- Reportes/
|   |   `-- Facturacion/
|   |-- Requests/
|   |-- Resources/
|   `-- Middleware/
|-- Models/
|   |-- Configuracion/
|   |-- Inventario/
|   |-- Compras/
|   |-- Ventas/
|   |-- Seguridad/
|   `-- Facturacion/
|-- Repositories/
|-- Services/
`-- Traits/
```

## Frontend

```text
resources/js/
|-- assets/
|-- components/
|-- layouts/
|-- router/
|-- services/
|-- stores/
|-- utils/
|-- views/
`-- composables/
```

## Modulos iniciales actuales

- `Configuracion`: `Sucursal`, `Puesto`, `Personal`, `Role`, `Permiso`
- `Inventario`: reservado
- `Compras`: reservado
- `Ventas`: reservado
- `Reportes`: reservado
- `Facturacion`: reservado para SIAT

## Flujo base

```text
Inertia View -> Controller -> Request -> Service -> Repository -> Model -> Resource
```

## Convivencia con la base actual

- `resources/js/app.ts` ya resuelve primero `views/` y deja `pages/` como fallback.
- Esto permite migrar modulo por modulo sin romper autenticacion ni vistas existentes.

## Ejemplo aplicado

- Controller: `App\Http\Controllers\Configuracion\SucursalController`
- Request: `App\Http\Requests\Configuracion\StoreSucursalRequest`
- Service: `App\Services\Configuracion\SucursalService`
- Repository: `App\Repositories\Configuracion\SucursalRepository`
- Model: `App\Models\Configuracion\Sucursal`
- Resource: `App\Http\Resources\Configuracion\SucursalResource`

## Modulos ya migrados al patron base

- `Sucursal`
- `Puesto`
- `Personal`
- `Permiso`
- `Role`

## Siguiente migracion sugerida

1. Reemplazar gradualmente el uso de modelos/controladores legacy por los namespaced de `Configuracion`.
2. Crear formularios reales sobre las vistas base de `Puesto`, `Personal`, `Permiso` y `Role`.
3. Añadir politicas/permisos de acceso y luego exponer API services especificos si necesitas consumo desacoplado.
