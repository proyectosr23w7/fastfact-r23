<?php

namespace App\Console\Commands;

use App\Enums\RolSistemaEnum;
use App\Enums\TipoFacturacionEnum;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use SimpleXMLElement;

class ImportLegacyProductionData extends Command
{
    protected $signature = 'fastfact:import-legacy-production
        {--database=fastfact_r23_legacy_import : Base temporal con el dump del sistema anterior}
        {--admin-email=proyectosr23w7@gmail.com}
        {--admin-password=12345678}
        {--ambiente=produccion : Ambiente de facturacion: produccion o piloto}
        {--tipo-facturacion=electronica : Tipo de facturacion: electronica o computarizada}
        {--legacy-user-domain=parqueo.local : Dominio usado para crear correos de usuarios legacy}
        {--legacy-user-password=12345678 : Password temporal para usuarios legacy}
        {--include-techdev-user : Importa tambien el usuario legacy TechDevAdmin}
        {--import-legacy-siat-codes : Importa CUIS/CUFD legacy aun cuando el ambiente sea piloto}';

    protected $description = 'Importa datos productivos del sistema anterior al esquema actual de FastFact.';

    private array $clienteMap = [];

    private array $productoMap = [];

    private array $puntoVentaMap = [];

    private int $adminId;

    private string $targetAmbiente;

    private int $targetTipoFacturacion;

    public function handle(): int
    {
        $this->configureLegacyConnection((string) $this->option('database'));
        $this->targetAmbiente = $this->resolveAmbiente();
        $this->targetTipoFacturacion = $this->resolveTipoFacturacion();

        if (! $this->legacyTableExists('datosempresa')) {
            $this->error('No se encontro la base temporal del sistema anterior.');

            return self::FAILURE;
        }

        DB::transaction(function (): void {
            $this->importSecurity();
            $this->importCompanyAndConfiguration();
            $this->importBranchesAndPoints();
            $this->importLegacyUsers();
            $this->importCatalogs();
            $this->importClients();
            $this->importProducts();
            $this->importCuisAndCufd();
            $this->importHistoricalInvoices();
        });

        $this->info('Importacion finalizada correctamente.');

        return self::SUCCESS;
    }

    private function configureLegacyConnection(string $database): void
    {
        Config::set('database.connections.legacy_import', array_merge(
            config('database.connections.mysql'),
            ['database' => $database],
        ));

        DB::purge('legacy_import');
    }

    private function legacyTableExists(string $table): bool
    {
        return DB::connection('legacy_import')->getSchemaBuilder()->hasTable($table);
    }

    private function importSecurity(): void
    {
        DB::table('users')->updateOrInsert(
            ['email' => (string) $this->option('admin-email')],
            [
                'name' => 'Administrador Proyectos R23W7',
                'password' => Hash::make((string) $this->option('admin-password')),
                'estado' => true,
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $this->adminId = (int) DB::table('users')
            ->where('email', (string) $this->option('admin-email'))
            ->value('id');

        DB::table('roles')->updateOrInsert(
            ['slug' => RolSistemaEnum::SUPERADMIN->value],
            [
                'nombre' => RolSistemaEnum::SUPERADMIN->label(),
                'descripcion' => 'Acceso total para pruebas oficiales de produccion.',
                'estado' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $roleId = (int) DB::table('roles')->where('slug', RolSistemaEnum::SUPERADMIN->value)->value('id');

        DB::table('role_user')->updateOrInsert(
            ['user_id' => $this->adminId, 'role_id' => $roleId],
            ['created_at' => now(), 'updated_at' => now()],
        );

        foreach (DB::table('permisos')->pluck('id') as $permissionId) {
            DB::table('permission_role')->updateOrInsert(
                ['role_id' => $roleId, 'permission_id' => $permissionId],
                ['created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    private function importCompanyAndConfiguration(): void
    {
        $empresa = DB::connection('legacy_import')->table('datosempresa')->first();
        $sistema = DB::connection('legacy_import')->table('datossistema')->first();

        DB::table('empresas')->updateOrInsert(
            ['id' => 1],
            [
                'nombre_empresa' => (string) ($empresa->nombreempresa ?? 'Proyectos R23W7'),
                'razon_social' => (string) ($empresa->razonsocial ?? $empresa->nombreempresa ?? 'Proyectos R23W7'),
                'propietario' => (string) ($empresa->razonsocial ?? null),
                'nit' => (string) ($empresa->nit ?? ''),
                'direccion' => $this->legacyValue('sucursal', 'direccion') ?: null,
                'telefono' => $this->legacyValue('sucursal', 'telefono') ?: null,
                'correo' => (string) $this->option('admin-email'),
                'estado' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        DB::table('configuraciones')->updateOrInsert(
            ['id' => 1],
            [
                'facturacion_habilitada' => true,
                'tipo_facturacion' => $this->targetTipoFacturacion,
                'ambiente_facturacion' => $this->targetAmbiente,
                'token_siat' => $this->targetAmbiente === 'produccion' ? (string) ($empresa->token ?? '') : '',
                'token_siat_produccion' => $this->targetAmbiente === 'produccion' ? (string) ($empresa->token ?? '') : null,
                'token_siat_piloto' => null,
                'codigo_sistema' => (string) ($sistema->codigo ?? ''),
                'tipo_impresion' => 'media_carta',
                'metodo_salida' => 'peps',
                'metodo_costos' => 'promedio_ponderado',
                'multiples_precios' => false,
                'precios_por_cantidad' => false,
                'productos_categorias_habilitadas' => true,
                'productos_marcas_habilitadas' => false,
                'productos_busqueda_avanzada_habilitada' => true,
                'productos_codigo_barras_habilitado' => true,
                'pagos_credito_habilitados' => false,
                'confirmacion_rapida_ventas' => true,
                'facturacion_obligatoria_ventas' => true,
                'estado' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    private function importBranchesAndPoints(): void
    {
        $legacySucursales = DB::connection('legacy_import')->table('sucursal')->orderBy('id')->get();

        foreach ($legacySucursales as $sucursal) {
            DB::table('sucursales')->updateOrInsert(
                ['codigo' => (int) $sucursal->codigoSucursal],
                [
                    'nombre' => (string) ($sucursal->descripcion ?: 'Sucursal '.$sucursal->codigoSucursal),
                    'direccion' => (string) $sucursal->direccion,
                    'telefono' => (string) $sucursal->telefono,
                    'estado' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }

        $legacyPuntos = DB::connection('legacy_import')->table('puntoventa')->orderBy('id_puntoVenta')->get();

        foreach ($legacyPuntos as $punto) {
            $sucursalId = (int) DB::table('sucursales')->where('codigo', (int) $punto->codigoSucursal)->value('id');

            DB::table('puntos_venta')->updateOrInsert(
                ['sucursal_id' => $sucursalId, 'codigo' => (int) $punto->codigoPuntoVenta],
                [
                    'nombre' => (string) ($punto->descripcion ?: 'Punto de venta '.$punto->codigoPuntoVenta),
                    'descripcion' => (string) ($punto->descripcion ?: null),
                    'tipo_impresion' => 'media_carta',
                    'estado' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );

            $this->puntoVentaMap[(int) $punto->id_puntoVenta] = (int) DB::table('puntos_venta')
                ->where('sucursal_id', $sucursalId)
                ->where('codigo', (int) $punto->codigoPuntoVenta)
                ->value('id');
        }
    }

    private function importCatalogs(): void
    {
        $this->copyCatalog('slistaactividades', 'sin_actividades', [
            'codigo_clasificador' => 'codigoCaeb',
            'descripcion' => 'descripcion',
        ]);
        $this->copyCatalog('slistaproductos', 'sin_productos_servicios', [
            'codigo_actividad' => 'codigoActividad',
            'codigo_producto' => 'codigoProducto',
            'descripcion' => 'descripcionProducto',
        ], 'codigo_producto');
        $this->copyCatalog('smotivoanulacion', 'sin_motivos_anulacion', [
            'codigo_clasificador' => 'codigo',
            'descripcion' => 'descripcion',
        ]);
        $this->copyCatalog('sleyendas', 'sin_leyendas', [
            'codigo_actividad' => 'codigoActividad',
            'descripcion_leyenda' => 'descripcionLeyenda',
        ], null);
        $this->copyCatalog('stipodocumentoidentidad', 'sin_documentos_identidad', [
            'codigo_clasificador' => 'codigo',
            'descripcion' => 'descripcion',
        ]);
        $this->copyCatalog('stipounidadesmedidas', 'sin_unidades_medida', [
            'codigo_clasificador' => 'codigo',
            'descripcion' => 'descripcion',
        ]);
        $this->copyCatalog('stipomodena', 'sin_monedas', [
            'codigo_clasificador' => 'codigo',
            'descripcion' => 'descripcion',
        ]);
        $this->copyCatalog('stipometodopagos', 'sin_metodos_pago', [
            'codigo_clasificador' => 'codigo',
            'descripcion' => 'descripcion',
        ]);

        DB::table('sin_metodos_pago')->update(['habilitado_venta' => true, 'es_predeterminado' => false]);
        DB::table('sin_metodos_pago')->where('codigo_clasificador', '1')->update(['es_predeterminado' => true]);
        DB::table('sin_unidades_medida')->update(['habilitado_uso' => true]);
    }

    private function copyCatalog(string $legacyTable, string $targetTable, array $map, ?string $unique = 'codigo_clasificador'): void
    {
        if (! $this->legacyTableExists($legacyTable)) {
            return;
        }

        foreach (DB::connection('legacy_import')->table($legacyTable)->get() as $row) {
            $payload = ['estado' => (int) ($row->estado ?? 1) === 1, 'created_at' => now(), 'updated_at' => now()];
            foreach ($map as $target => $source) {
                $payload[$target] = (string) ($row->{$source} ?? '');
            }

            if ($unique) {
                DB::table($targetTable)->updateOrInsert([$unique => $payload[$unique]], $payload);
            } else {
                DB::table($targetTable)->insert($payload);
            }
        }
    }

    private function importLegacyUsers(): void
    {
        if (! $this->legacyTableExists('usuarios')) {
            return;
        }

        $this->ensureOperationalRoles();

        $domain = trim((string) $this->option('legacy-user-domain')) ?: 'parqueo.local';
        $password = (string) $this->option('legacy-user-password');
        $includeTechDev = (bool) $this->option('include-techdev-user');

        foreach (DB::connection('legacy_import')->table('usuarios')->orderBy('id_usuario')->get() as $legacyUser) {
            $nick = trim((string) $legacyUser->nick);

            if (! $includeTechDev && Str::lower($nick) === 'techdevadmin') {
                continue;
            }

            $roleSlug = str_contains(Str::lower($nick), 'admin')
                ? RolSistemaEnum::ADMINISTRADOR->value
                : RolSistemaEnum::CAJERO->value;

            $email = $this->emailForLegacyUser($nick, $domain);

            DB::table('users')->updateOrInsert(
                ['email' => $email],
                [
                    'name' => (string) ($legacyUser->nombre ?: $nick ?: $email),
                    'password' => Hash::make($password),
                    'sucursal_id' => $this->sucursalIdForLegacyUser($legacyUser),
                    'punto_venta_id' => $this->puntoVentaIdForLegacyUser($legacyUser),
                    'estado' => (int) $legacyUser->usuario_estado === 1,
                    'email_verified_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );

            $userId = (int) DB::table('users')->where('email', $email)->value('id');
            $roleId = (int) DB::table('roles')->where('slug', $roleSlug)->value('id');

            if ($userId && $roleId) {
                DB::table('role_user')->updateOrInsert(
                    ['user_id' => $userId, 'role_id' => $roleId],
                    ['created_at' => now(), 'updated_at' => now()],
                );
            }
        }
    }

    private function ensureOperationalRoles(): void
    {
        foreach ([RolSistemaEnum::ADMINISTRADOR, RolSistemaEnum::CAJERO] as $role) {
            DB::table('roles')->updateOrInsert(
                ['slug' => $role->value],
                [
                    'nombre' => $role->label(),
                    'descripcion' => $role === RolSistemaEnum::ADMINISTRADOR
                        ? 'Administra la operacion comercial.'
                        : 'Emite facturas y gestiona datos operativos basicos.',
                    'estado' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }

    private function importClients(): void
    {
        foreach (DB::connection('legacy_import')->table('clientes')->orderBy('id_cliente')->get() as $cliente) {
            DB::table('clientes')->updateOrInsert(
                ['codigo' => 'LEG-'.$cliente->id_cliente],
                [
                    'nombre' => (string) ($cliente->razon_social ?: $cliente->documentoid),
                    'razon_social' => (string) ($cliente->razon_social ?: 'SIN NOMBRE'),
                    'nit_ci' => (string) $cliente->documentoid,
                    'tipo_documento_identidad' => (string) $cliente->tipoDocumento,
                    'complemento' => filled($cliente->complementoid) ? (string) $cliente->complementoid : null,
                    'telefono' => (string) ($cliente->cliente_celular ?: ''),
                    'correo' => (string) ($cliente->cliente_email ?: ''),
                    'estado' => (int) $cliente->cliente_estado === 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );

            $this->clienteMap[(int) $cliente->id_cliente] = (int) DB::table('clientes')
                ->where('codigo', 'LEG-'.$cliente->id_cliente)
                ->value('id');
        }
    }

    private function importProducts(): void
    {
        $categoriaId = $this->ensureCategory('Servicios y productos migrados');
        $marcaId = $this->ensureBrand('Generica');
        $homologacion = $this->inferProductHomologation();

        foreach (DB::connection('legacy_import')->table('productos')->orderBy('id_producto')->get() as $producto) {
            $unidadId = $this->ensureUnit((string) $producto->id_medida);
            $codigoOriginal = trim((string) $producto->codigo);
            $codigo = $codigoOriginal;

            if (DB::table('articulos')->where('codigo_generico', $codigo)->exists()) {
                $codigo = $codigoOriginal.'-LEG-'.$producto->id_producto;
            }

            DB::table('articulos')->updateOrInsert(
                ['codigo_generico' => $codigo],
                [
                    'codigo_barras' => strlen($codigo) >= 8 && ctype_digit($codigo) ? $codigo : null,
                    'nombre' => (string) $producto->nombre_producto,
                    'descripcion' => (string) $producto->nombre_producto,
                    'categoria_id' => $categoriaId,
                    'marca_id' => $marcaId,
                    'unidad_medida_id' => $unidadId,
                    'codigo_actividad_economica' => $homologacion[$codigo]['actividad'] ?? '620100',
                    'codigo_producto_sin' => $homologacion[$codigo]['producto_sin'] ?? null,
                    'codigo_unidad_medida_siat' => (string) $producto->id_medida,
                    'stock_minimo' => 0,
                    'stock_actual' => 0,
                    'costo' => 0,
                    'precio_base' => (float) $producto->precio_venta,
                    'estado' => (int) $producto->producto_estado === 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );

            $articuloId = (int) DB::table('articulos')->where('codigo_generico', $codigo)->value('id');
            $this->productoMap[(int) $producto->id_producto] = $articuloId;
            $this->productoMap[$codigoOriginal] ??= $articuloId;

            DB::table('articulo_precios')->updateOrInsert(
                ['articulo_id' => $articuloId, 'cantidad_minima' => 1, 'tipo_precio' => 'general'],
                ['precio' => (float) $producto->precio_venta, 'estado' => true, 'created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    private function importCuisAndCufd(): void
    {
        if ($this->targetAmbiente !== 'produccion' && ! (bool) $this->option('import-legacy-siat-codes')) {
            return;
        }

        foreach (DB::connection('legacy_import')->table('puntoventa')->get() as $punto) {
            $puntoVentaId = $this->puntoVentaMap[(int) $punto->id_puntoVenta];
            $sucursalId = (int) DB::table('puntos_venta')->where('id', $puntoVentaId)->value('sucursal_id');

            DB::table('cuis')->updateOrInsert(
                ['codigo' => (string) $punto->cuis, 'punto_venta_id' => $puntoVentaId, 'ambiente_facturacion' => $this->targetAmbiente],
                [
                    'sucursal_id' => $sucursalId,
                    'fecha_vigencia' => $this->dateOrNull($punto->vigenciaCuis),
                    'estado' => true,
                    'codigo_respuesta' => 'MIGRADO',
                    'descripcion_respuesta' => 'CUIS vigente migrado desde sistema anterior.',
                    'user_id' => $this->adminId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }

        foreach (DB::connection('legacy_import')->table('cufd')->orderBy('id_cufd')->get() as $cufd) {
            $puntoVentaId = $this->puntoVentaMap[(int) $cufd->id_puntoventa] ?? null;
            if (! $puntoVentaId) {
                continue;
            }

            $sucursalId = (int) DB::table('puntos_venta')->where('id', $puntoVentaId)->value('sucursal_id');
            DB::table('cufd')->insert([
                'codigo' => (string) $cufd->codigo,
                'codigo_control' => (string) $cufd->codigoControl,
                'direccion' => $this->legacyValue('sucursal', 'direccion'),
                'sucursal_id' => $sucursalId,
                'punto_venta_id' => $puntoVentaId,
                'ambiente_facturacion' => $this->targetAmbiente,
                'fecha_vigencia' => $this->dateOrNull($cufd->fechaVigencia),
                'estado' => Carbon::parse($cufd->fechaVigencia)->isFuture(),
                'codigo_respuesta' => 'MIGRADO',
                'descripcion_respuesta' => 'CUFD historico migrado desde sistema anterior.',
                'user_id' => $this->adminId,
                'created_at' => $this->dateOrNull($cufd->fechaSolicitud) ?: now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function importHistoricalInvoices(): void
    {
        foreach (DB::connection('legacy_import')->table('facturas')->orderBy('id_factura')->get() as $factura) {
            $clienteId = $this->clienteMap[(int) $factura->id_cliente] ?? null;
            $puntoVentaId = $this->puntoVentaMap[(int) $factura->puntoventa] ?? reset($this->puntoVentaMap);
            $sucursalId = (int) DB::table('puntos_venta')->where('id', $puntoVentaId)->value('sucursal_id');
            $fecha = $this->dateOrNull($factura->fechaEmision) ?: now();
            $estado = (int) $factura->anulacion === 1 ? 'anulada' : 'emitida';
            $detalles = $this->extractInvoiceDetails((string) $factura->productos);

            $cuisId = (int) DB::table('cuis')->where('codigo', (string) $factura->cuis)->value('id') ?: null;
            $cufdId = (int) DB::table('cufd')->where('codigo', (string) $factura->cufd)->latest('id')->value('id') ?: null;

            $facturaId = DB::table('facturas')->insertGetId([
                'origen' => 'legacy',
                'referencia_externa' => (string) $factura->id_factura,
                'cliente_id' => $clienteId,
                'sucursal_id' => $sucursalId,
                'punto_venta_id' => $puntoVentaId,
                'user_id' => $this->adminId,
                'cuis_id' => $cuisId,
                'cufd_id' => $cufdId,
                'numero_factura' => (int) $factura->numeroFactura,
                'cuf' => (string) $factura->cuf,
                'codigo_recepcion' => (string) $factura->codigoRecepcion,
                'codigo_metodo_pago' => (string) $factura->codigoMetodoPago,
                'codigo_documento_identidad' => $this->xmlValue((string) $factura->productos, 'codigoTipoDocumentoIdentidad'),
                'tipo_facturacion' => $this->targetTipoFacturacion,
                'ambiente_facturacion' => $this->targetAmbiente,
                'codigo_emision' => (int) $factura->tipoEmision,
                'xml_fiscal' => (string) $factura->productos,
                // La ruta del servidor anterior no es valida en la nueva instalacion.
                'pdf_path' => null,
                'hash_xml' => hash('sha256', (string) $factura->productos),
                'fecha_emision' => $fecha,
                'monto_total' => (float) $factura->montoTotal,
                'monto_sujeto_iva' => (float) $factura->montoTotalSujetoIVA,
                'monto_gift_card' => 0,
                'descuento_global' => (float) $factura->descuentoAdicional,
                'codigo_estado' => 'MIGRADO',
                'descripcion_estado' => 'Factura historica migrada desde sistema anterior.',
                'estado_factura' => $estado,
                'estado_sincronizacion' => 'sincronizada',
                'codigo_excepcion' => $this->xmlValue((string) $factura->productos, 'codigoExcepcion'),
                'datos_respuesta_siat' => json_encode(['codigoRecepcion' => (string) $factura->codigoRecepcion]),
                'metadata' => json_encode([
                    'legacy_id' => $factura->id_factura,
                    'legacy_pdf_path' => (string) $factura->raiz,
                ]),
                'created_at' => $fecha,
                'updated_at' => now(),
            ]);

            foreach ($detalles as $detalle) {
                $articuloId = $this->productoMap[$detalle['codigo_producto']] ?? null;

                DB::table('factura_detalles')->insert([
                    'factura_id' => $facturaId,
                    'articulo_id' => $articuloId,
                    'actividad_economica' => $detalle['actividad'],
                    'codigo_producto_sin' => (int) $detalle['producto_sin'],
                    'codigo_producto' => $detalle['codigo_producto'],
                    'descripcion' => $detalle['descripcion'],
                    'cantidad' => $detalle['cantidad'],
                    'unidad_medida' => (int) $detalle['unidad_medida'],
                    'precio_unitario' => $detalle['precio_unitario'],
                    'monto_descuento' => $detalle['descuento'],
                    'subtotal' => $detalle['subtotal'],
                    'metadata' => json_encode(['legacy_xml' => true]),
                    'created_at' => $fecha,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function inferProductHomologation(): array
    {
        $result = [];
        foreach (DB::connection('legacy_import')->table('facturas')->get(['productos']) as $factura) {
            foreach ($this->extractInvoiceDetails((string) $factura->productos) as $detalle) {
                $result[$detalle['codigo_producto']] = [
                    'actividad' => $detalle['actividad'],
                    'producto_sin' => $detalle['producto_sin'],
                ];
            }
        }

        return $result;
    }

    private function extractInvoiceDetails(string $xml): array
    {
        try {
            $document = new SimpleXMLElement($xml);
        } catch (\Throwable) {
            return [];
        }

        $items = [];
        foreach ($document->detalle as $detalle) {
            $items[] = [
                'actividad' => (string) $detalle->actividadEconomica,
                'producto_sin' => (string) $detalle->codigoProductoSin,
                'codigo_producto' => (string) $detalle->codigoProducto,
                'descripcion' => (string) $detalle->descripcion,
                'cantidad' => (float) $detalle->cantidad,
                'unidad_medida' => (int) $detalle->unidadMedida,
                'precio_unitario' => (float) $detalle->precioUnitario,
                'descuento' => (float) $detalle->montoDescuento,
                'subtotal' => (float) $detalle->subTotal,
            ];
        }

        return $items;
    }

    private function xmlValue(string $xml, string $field): ?string
    {
        try {
            $document = new SimpleXMLElement($xml);

            return isset($document->cabecera->{$field}) ? (string) $document->cabecera->{$field} : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function ensureCategory(string $name): int
    {
        DB::table('categorias')->updateOrInsert(
            ['nombre' => $name],
            ['descripcion' => 'Categoria creada para datos migrados.', 'estado' => true, 'created_at' => now(), 'updated_at' => now()],
        );

        return (int) DB::table('categorias')->where('nombre', $name)->value('id');
    }

    private function ensureBrand(string $name): int
    {
        DB::table('marcas')->updateOrInsert(
            ['nombre' => $name],
            ['descripcion' => 'Marca generica para datos migrados.', 'estado' => true, 'created_at' => now(), 'updated_at' => now()],
        );

        return (int) DB::table('marcas')->where('nombre', $name)->value('id');
    }

    private function ensureUnit(string $codigoSiat): int
    {
        $descripcion = DB::connection('legacy_import')
            ->table('stipounidadesmedidas')
            ->where('codigo', $codigoSiat)
            ->value('descripcion') ?: 'Unidad SIAT '.$codigoSiat;

        DB::table('unidades_medida')->updateOrInsert(
            ['abreviatura' => 'SIAT-'.$codigoSiat],
            ['nombre' => Str::title(Str::lower((string) $descripcion)), 'estado' => true, 'created_at' => now(), 'updated_at' => now()],
        );

        return (int) DB::table('unidades_medida')->where('abreviatura', 'SIAT-'.$codigoSiat)->value('id');
    }

    private function legacyValue(string $table, string $column): mixed
    {
        return DB::connection('legacy_import')->table($table)->value($column);
    }

    private function dateOrNull(mixed $value): ?Carbon
    {
        if (! filled($value)) {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->timezone(config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveAmbiente(): string
    {
        $ambiente = Str::lower(trim((string) $this->option('ambiente')));

        return in_array($ambiente, ['piloto', 'produccion'], true) ? $ambiente : 'produccion';
    }

    private function resolveTipoFacturacion(): int
    {
        $tipo = Str::lower(trim((string) $this->option('tipo-facturacion')));

        return $tipo === 'computarizada'
            ? TipoFacturacionEnum::COMPUTARIZADA->value
            : TipoFacturacionEnum::ELECTRONICA->value;
    }

    private function emailForLegacyUser(string $nick, string $domain): string
    {
        $local = Str::of($nick)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '.')
            ->trim('.');

        if ($local->isEmpty()) {
            $local = Str::of('usuario');
        }

        return $local.'@'.$domain;
    }

    private function puntoVentaIdForLegacyUser(object $legacyUser): ?int
    {
        $legacyPuntoVentaId = (int) ($legacyUser->id_puntoventa ?? 0);

        return $legacyPuntoVentaId > 0 && isset($this->puntoVentaMap[$legacyPuntoVentaId])
            ? (int) $this->puntoVentaMap[$legacyPuntoVentaId]
            : null;
    }

    private function sucursalIdForLegacyUser(object $legacyUser): ?int
    {
        $puntoVentaId = $this->puntoVentaIdForLegacyUser($legacyUser);

        return $puntoVentaId
            ? (int) DB::table('puntos_venta')->where('id', $puntoVentaId)->value('sucursal_id')
            : null;
    }
}
