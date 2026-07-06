<?php

namespace App\Services\Facturacion;

use App\Enums\TipoFacturacionEnum;
use App\Models\Configuracion\Configuracion;

class SiatEndpointResolver
{
    public function profile(?Configuracion $configuracion = null): array
    {
        $configuracion ??= Configuracion::current();

        $environmentKey = $this->normalizeEnvironment($configuracion?->ambiente_facturacion);
        $environmentConfig = config("siat.environments.{$environmentKey}", []);
        $modules = $this->modules($environmentKey);

        return [
            'environment' => [
                'key' => $environmentKey,
                'label' => $environmentConfig['label'] ?? ucfirst($environmentKey),
                'codigo_ambiente' => $environmentConfig['codigo_ambiente'] ?? null,
            ],
            'auth' => [
                'token_header' => $environmentConfig['token_header'] ?? 'Authorization',
                'token_prefix' => $environmentConfig['token_prefix'] ?? 'Token',
                'token_configurado' => filled($configuracion?->tokenSiatActivo()),
                'token_vigencia' => optional($configuracion?->tokenSiatVigenciaActiva())?->format('Y-m-d H:i:s'),
                'codigo_sistema_configurado' => filled($configuracion?->codigo_sistema),
            ],
            'modules' => $modules,
            'tipo_facturacion' => [
                'codigo' => $configuracion?->tipo_facturacion,
                'label' => $this->tipoFacturacionLabel($configuracion?->tipo_facturacion),
                'module_key' => $this->facturacionModuleKey($configuracion?->tipo_facturacion),
            ],
            'ready_for_live_calls' => filled($configuracion?->tokenSiatActivo()) && filled($configuracion?->codigo_sistema),
        ];
    }

    public function resolve(string $module, ?Configuracion $configuracion = null): array
    {
        $profile = $this->profile($configuracion);
        $resolved = collect($profile['modules'])->firstWhere('key', $module);

        return $resolved ?? [
            'key' => $module,
            'label' => $this->moduleLabel($module),
            'wsdl' => null,
            'configured' => false,
        ];
    }

    public function facturacionModule(?int $tipoFacturacion, ?Configuracion $configuracion = null): array
    {
        $module = $this->facturacionModuleKey($tipoFacturacion);

        if ($module === null) {
            return [
                'key' => null,
                'label' => 'No emite factura',
                'wsdl' => null,
                'configured' => false,
            ];
        }

        return $this->resolve($module, $configuracion);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function modules(string $environmentKey): array
    {
        $modules = config("siat.environments.{$environmentKey}.modules", []);

        return collect($modules)
            ->map(fn ($wsdl, $key) => [
                'key' => $key,
                'label' => $this->moduleLabel((string) $key),
                'wsdl' => $wsdl,
                'configured' => filled($wsdl),
            ])
            ->values()
            ->all();
    }

    private function normalizeEnvironment(?string $environment): string
    {
        return in_array($environment, ['piloto', 'produccion'], true) ? $environment : 'piloto';
    }

    private function moduleLabel(string $module): string
    {
        return (string) (config("siat.module_labels.{$module}") ?? str_replace('_', ' ', ucfirst($module)));
    }

    private function facturacionModuleKey(?int $tipoFacturacion): ?string
    {
        return match ($tipoFacturacion) {
            TipoFacturacionEnum::ELECTRONICA->value => 'facturacion_electronica',
            TipoFacturacionEnum::COMPUTARIZADA->value => 'facturacion_computarizada',
            default => null,
        };
    }

    private function tipoFacturacionLabel(?int $tipoFacturacion): string
    {
        return match ($tipoFacturacion) {
            TipoFacturacionEnum::ELECTRONICA->value => 'Factura electronica en linea',
            TipoFacturacionEnum::COMPUTARIZADA->value => 'Factura computarizada en linea',
            default => 'No emite factura',
        };
    }
}
