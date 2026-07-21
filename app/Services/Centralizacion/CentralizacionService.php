<?php

namespace App\Services\Centralizacion;

use App\Models\Configuracion\Configuracion;
use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\PuntoVenta;
use App\Models\Cufd;
use App\Models\Cuis;
use App\Models\Factura;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;
use ZipArchive;

class CentralizacionService
{
    private const WARNING_DAYS = 15;

    private const BACKUP_DISK = 'local';

    private const BACKUP_DIRECTORY = 'backups';

    public function estado(): array
    {
        $configuracion = Configuracion::current();
        $empresa = Empresa::query()->first();

        return [
            'generated_at' => now(config('app.timezone'))->toIso8601String(),
            'app' => [
                'name' => config('app.name'),
                'url' => config('app.url'),
                'timezone' => config('app.timezone'),
                'environment' => app()->environment(),
            ],
            'empresa' => [
                'nombre' => $empresa?->nombre_empresa,
                'razon_social' => $empresa?->razon_social,
                'nit' => $empresa?->nit,
                'correo' => $empresa?->correo,
                'telefono' => $empresa?->telefono,
            ],
            'facturacion' => [
                'habilitada' => (bool) ($configuracion?->facturacion_habilitada ?? false),
                'ambiente' => $configuracion?->ambiente_facturacion,
                'tipo_facturacion' => $configuracion?->tipo_facturacion,
                'codigo_sistema_configurado' => filled($configuracion?->codigo_sistema),
            ],
            'credenciales' => [
                'tokens_siat' => $this->tokens($configuracion),
                'firma_digital' => $this->firmaDigital($configuracion),
            ],
            'siat' => [
                'cuis' => $this->credencialesPuntosVenta(Cuis::class),
                'cufd' => $this->credencialesPuntosVenta(Cufd::class),
            ],
            'operacion' => [
                'facturas_total' => Factura::query()->count(),
                'facturas_pendientes_envio' => Factura::query()->where('estado_factura', 'pendiente_envio')->count(),
                'facturas_observadas' => Factura::query()->whereIn('estado_factura', ['observada', 'rechazada'])->count(),
            ],
            'respaldo' => $this->respaldoManifest(),
        ];
    }

    public function respaldoManifest(): array
    {
        return [
            'database' => [
                'connection' => DB::connection()->getName(),
                'database' => DB::connection()->getDatabaseName(),
                'driver' => DB::connection()->getDriverName(),
                'tables' => $this->databaseTables(),
                'recommended_frequency' => 'diario',
            ],
            'files' => [
                [
                    'disk' => 'local',
                    'path' => 'siat',
                    'exists' => Storage::disk('local')->exists('siat'),
                    'description' => 'Certificados, XML y archivos fiscales privados segun configuracion local.',
                ],
                [
                    'disk' => 'local',
                    'path' => 'private',
                    'exists' => Storage::disk('local')->exists('private'),
                    'description' => 'Archivos privados generados por el sistema.',
                ],
                [
                    'disk' => 'public',
                    'path' => '',
                    'exists' => true,
                    'description' => 'Archivos publicos cargados, como logos si aplica.',
                ],
            ],
            'excludes' => [
                '.env',
                'vendor',
                'node_modules',
                'storage/logs',
                'bootstrap/cache',
            ],
            'automation_ready' => true,
            'download_endpoint' => '/api/centralizacion/backups/{filename}',
            'generate_endpoint' => '/api/centralizacion/backups',
        ];
    }

    public function listarBackups(): array
    {
        Storage::disk(self::BACKUP_DISK)->makeDirectory(self::BACKUP_DIRECTORY);

        return collect(Storage::disk(self::BACKUP_DISK)->files(self::BACKUP_DIRECTORY))
            ->filter(fn (string $path) => str_ends_with($path, '.zip'))
            ->map(fn (string $path) => $this->backupMetadata($path))
            ->sortByDesc('created_at')
            ->values()
            ->all();
    }

    public function generarBackup(): array
    {
        abort_unless(class_exists(ZipArchive::class), 422, 'La extension ZIP de PHP no esta habilitada en el servidor.');
        abort_unless(DB::connection()->getDriverName() === 'mysql', 422, 'La generacion de backup SQL esta disponible para MySQL/MariaDB.');

        Storage::disk(self::BACKUP_DISK)->makeDirectory(self::BACKUP_DIRECTORY);

        $timestamp = now(config('app.timezone'))->format('Ymd-His');
        $database = preg_replace('/[^A-Za-z0-9_\-]/', '_', DB::connection()->getDatabaseName());
        $filename = "fastfact-r23-{$database}-{$timestamp}.zip";
        $relativePath = self::BACKUP_DIRECTORY.'/'.$filename;
        $absolutePath = Storage::disk(self::BACKUP_DISK)->path($relativePath);
        $tmpDirectory = self::BACKUP_DIRECTORY.'/tmp-'.$timestamp.'-'.bin2hex(random_bytes(4));
        $tmpSqlPath = $tmpDirectory.'/database.sql';
        $tmpManifestPath = $tmpDirectory.'/manifest.json';

        Storage::disk(self::BACKUP_DISK)->makeDirectory($tmpDirectory);

        try {
            $this->writeDatabaseDump(Storage::disk(self::BACKUP_DISK)->path($tmpSqlPath));

            Storage::disk(self::BACKUP_DISK)->put($tmpManifestPath, json_encode([
                'generated_at' => now(config('app.timezone'))->toIso8601String(),
                'app' => [
                    'name' => config('app.name'),
                    'url' => config('app.url'),
                ],
                'database' => [
                    'connection' => DB::connection()->getName(),
                    'database' => DB::connection()->getDatabaseName(),
                    'driver' => DB::connection()->getDriverName(),
                ],
                'included' => [
                    'database.sql',
                    'manifest.json',
                    'storage/app/siat',
                    'storage/app/private',
                    'storage/app/public',
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $zip = new ZipArchive;
            abort_unless($zip->open($absolutePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, 422, 'No se pudo crear el archivo ZIP de respaldo.');

            $zip->addFile(Storage::disk(self::BACKUP_DISK)->path($tmpSqlPath), 'database.sql');
            $zip->addFile(Storage::disk(self::BACKUP_DISK)->path($tmpManifestPath), 'manifest.json');
            $this->addStorageDirectoryToZip($zip, 'siat', 'storage/app/siat');
            $this->addStorageDirectoryToZip($zip, 'private', 'storage/app/private');
            $this->addStorageDirectoryToZip($zip, 'public', 'storage/app/public', 'public');
            $zip->close();

            return $this->backupMetadata($relativePath);
        } finally {
            Storage::disk(self::BACKUP_DISK)->deleteDirectory($tmpDirectory);
        }
    }

    public function backupPath(string $filename): string
    {
        $filename = basename($filename);

        abort_unless(str_ends_with($filename, '.zip'), 404, 'Backup no encontrado.');

        $path = self::BACKUP_DIRECTORY.'/'.$filename;

        abort_unless(Storage::disk(self::BACKUP_DISK)->exists($path), 404, 'Backup no encontrado.');

        return Storage::disk(self::BACKUP_DISK)->path($path);
    }

    private function tokens(?Configuracion $configuracion): array
    {
        return [
            'piloto' => $this->tokenStatus(
                filled($configuracion?->token_siat_piloto ?: $configuracion?->token_siat),
                $configuracion?->token_siat_piloto_vigencia,
            ),
            'produccion' => $this->tokenStatus(
                filled($configuracion?->token_siat_produccion ?: $configuracion?->token_siat),
                $configuracion?->token_siat_produccion_vigencia,
            ),
            'ambiente_activo' => $configuracion?->ambiente_facturacion,
            'activo' => $this->tokenStatus(
                filled($configuracion?->tokenSiatActivo()),
                $configuracion?->tokenSiatVigenciaActiva(),
            ),
        ];
    }

    private function tokenStatus(bool $configurado, mixed $vigencia): array
    {
        return [
            'configurado' => $configurado,
            'vigencia' => $vigencia instanceof Carbon ? $vigencia->toIso8601String() : null,
            ...$this->expiryStatus($configurado, $vigencia),
        ];
    }

    private function firmaDigital(?Configuracion $configuracion): array
    {
        $path = $configuracion?->firma_digital_path;
        $archivoPresente = filled($path) && Storage::disk('local')->exists((string) $path);
        $certificado = $this->certificateStatus($configuracion);

        return [
            'configurada' => (bool) ($configuracion?->firmaDigitalConfigurada() ?? false),
            'nombre' => $configuracion?->firma_digital_nombre,
            'archivo_configurado' => filled($path),
            'archivo_presente' => $archivoPresente,
            'vencimiento' => $certificado['vencimiento'],
            'dias_para_vencer' => $certificado['dias_para_vencer'],
            'estado' => $certificado['estado'],
            'detalle' => $certificado['detalle'],
        ];
    }

    private function certificateStatus(?Configuracion $configuracion): array
    {
        if (! $configuracion?->firmaDigitalConfigurada()) {
            return [
                'vencimiento' => null,
                'dias_para_vencer' => null,
                'estado' => 'sin_configurar',
                'detalle' => 'No existe una firma digital completamente configurada.',
            ];
        }

        if (! function_exists('openssl_pkcs12_read') || ! function_exists('openssl_x509_parse')) {
            return [
                'vencimiento' => null,
                'dias_para_vencer' => null,
                'estado' => 'no_verificable',
                'detalle' => 'La extension OpenSSL no permite leer la vigencia del certificado.',
            ];
        }

        try {
            $content = file_get_contents((string) $configuracion->firmaDigitalAbsolutePath());
            $certs = [];

            if (! openssl_pkcs12_read((string) $content, $certs, (string) $configuracion->firma_digital_password)) {
                return [
                    'vencimiento' => null,
                    'dias_para_vencer' => null,
                    'estado' => 'no_verificable',
                    'detalle' => 'No se pudo leer el P12/PFX con la contrasena configurada.',
                ];
            }

            $parsed = openssl_x509_parse((string) ($certs['cert'] ?? ''));
            $validTo = isset($parsed['validTo_time_t']) ? Carbon::createFromTimestamp((int) $parsed['validTo_time_t']) : null;

            return [
                'vencimiento' => $validTo?->toIso8601String(),
                ...$this->expiryStatus(true, $validTo),
                'detalle' => $validTo ? 'Vigencia leida desde el certificado digital.' : 'No se pudo identificar la vigencia del certificado.',
            ];
        } catch (Throwable $exception) {
            return [
                'vencimiento' => null,
                'dias_para_vencer' => null,
                'estado' => 'no_verificable',
                'detalle' => $exception->getMessage(),
            ];
        }
    }

    private function credencialesPuntosVenta(string $modelClass): array
    {
        $puntos = PuntoVenta::query()
            ->with('sucursal:id,codigo,nombre')
            ->where('estado', true)
            ->orderBy('sucursal_id')
            ->orderBy('codigo')
            ->get();

        $items = $puntos->map(function (PuntoVenta $puntoVenta) use ($modelClass): array {
            $registro = $modelClass::query()
                ->where('punto_venta_id', $puntoVenta->id)
                ->where('sucursal_id', $puntoVenta->sucursal_id)
                ->where('estado', true)
                ->orderByDesc('fecha_vigencia')
                ->orderByDesc('id')
                ->first();

            return [
                'sucursal' => [
                    'id' => $puntoVenta->sucursal?->id,
                    'codigo' => $puntoVenta->sucursal?->codigo,
                    'nombre' => $puntoVenta->sucursal?->nombre,
                ],
                'punto_venta' => [
                    'id' => $puntoVenta->id,
                    'codigo' => $puntoVenta->codigo,
                    'nombre' => $puntoVenta->nombre,
                ],
                'ambiente' => $registro?->ambiente_facturacion,
                'configurado' => $registro !== null,
                'vigencia' => $registro?->fecha_vigencia?->toIso8601String(),
                ...$this->expiryStatus($registro !== null, $registro?->fecha_vigencia),
            ];
        })->values();

        return [
            'total_puntos_venta' => $items->count(),
            'items' => $items->all(),
            'resumen' => $items->countBy('estado')->all(),
        ];
    }

    private function expiryStatus(bool $configurado, mixed $expiresAt): array
    {
        if (! $configurado) {
            return ['estado' => 'sin_configurar', 'dias_para_vencer' => null];
        }

        if (! $expiresAt instanceof Carbon) {
            return ['estado' => 'sin_vigencia', 'dias_para_vencer' => null];
        }

        $days = (int) now(config('app.timezone'))
            ->startOfDay()
            ->diffInDays($expiresAt->copy()->timezone(config('app.timezone'))->startOfDay(), false);

        return [
            'estado' => match (true) {
                $days < 0 => 'vencido',
                $days <= self::WARNING_DAYS => 'por_vencer',
                default => 'vigente',
            },
            'dias_para_vencer' => $days,
        ];
    }

    private function databaseTables(): array
    {
        try {
            return collect(DB::select('SHOW FULL TABLES'))
                ->map(function (object $row): ?string {
                    $values = array_values((array) $row);

                    return ($values[1] ?? null) === 'BASE TABLE' ? (string) ($values[0] ?? '') : null;
                })
                ->filter()
                ->values()
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    private function backupMetadata(string $path): array
    {
        $filename = basename($path);

        return [
            'filename' => $filename,
            'path' => $path,
            'size_bytes' => Storage::disk(self::BACKUP_DISK)->size($path),
            'size_mb' => round(Storage::disk(self::BACKUP_DISK)->size($path) / 1024 / 1024, 2),
            'created_at' => Carbon::createFromTimestamp(Storage::disk(self::BACKUP_DISK)->lastModified($path))
                ->timezone(config('app.timezone'))
                ->toIso8601String(),
            'download_url' => url('/api/centralizacion/backups/'.$filename),
        ];
    }

    private function writeDatabaseDump(string $absolutePath): void
    {
        $handle = fopen($absolutePath, 'wb');

        abort_unless(is_resource($handle), 422, 'No se pudo preparar el archivo SQL de respaldo.');

        try {
            fwrite($handle, "-- FastFact R23 backup\n");
            fwrite($handle, '-- Generated at: '.now(config('app.timezone'))->toIso8601String()."\n");
            fwrite($handle, '-- Database: '.DB::connection()->getDatabaseName()."\n\n");
            fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n");
            fwrite($handle, "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");

            foreach ($this->databaseTables() as $table) {
                $this->writeTableDump($handle, $table);
            }

            fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
        } finally {
            fclose($handle);
        }
    }

    private function writeTableDump(mixed $handle, string $table): void
    {
        $quotedTable = $this->quoteIdentifier($table);
        $createRows = DB::select("SHOW CREATE TABLE {$quotedTable}");
        $createSql = (string) collect((array) ($createRows[0] ?? []))->last();

        fwrite($handle, "\n-- --------------------------------------------------------\n");
        fwrite($handle, "-- Table {$table}\n");
        fwrite($handle, "DROP TABLE IF EXISTS {$quotedTable};\n");
        fwrite($handle, $createSql.";\n\n");

        $pdo = DB::connection()->getPdo();
        $statement = $pdo->query("SELECT * FROM {$quotedTable}");

        if (! $statement) {
            return;
        }

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $columns = array_map(fn (string $column) => $this->quoteIdentifier($column), array_keys($row));
            $values = array_map(fn (mixed $value) => $value === null ? 'NULL' : $pdo->quote((string) $value), array_values($row));

            fwrite($handle, 'INSERT INTO '.$quotedTable.' ('.implode(', ', $columns).') VALUES ('.implode(', ', $values).");\n");
        }

        fwrite($handle, "\n");
    }

    private function addStorageDirectoryToZip(ZipArchive $zip, string $path, string $zipPrefix, string $disk = self::BACKUP_DISK): void
    {
        if ($path !== '' && ! Storage::disk($disk)->exists($path)) {
            return;
        }

        foreach (Storage::disk($disk)->allFiles($path) as $file) {
            $relative = trim($file, '/');
            $localPath = Storage::disk($disk)->path($file);

            if (! is_file($localPath)) {
                continue;
            }

            $zip->addFile($localPath, $zipPrefix.'/'.($path === '' ? $relative : substr($relative, strlen(trim($path, '/')) + 1)));
        }
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`'.str_replace('`', '``', $identifier).'`';
    }
}
