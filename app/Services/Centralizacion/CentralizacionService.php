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

class CentralizacionService
{
    private const WARNING_DAYS = 15;

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
            'download_endpoint_reserved' => '/api/centralizacion/backups',
        ];
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

        $days = now(config('app.timezone'))->startOfDay()->diffInDays($expiresAt->copy()->startOfDay(), false);

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
            return collect(DB::select('SHOW TABLES'))
                ->map(fn (object $row) => (string) collect((array) $row)->first())
                ->filter()
                ->values()
                ->all();
        } catch (Throwable) {
            return [];
        }
    }
}
