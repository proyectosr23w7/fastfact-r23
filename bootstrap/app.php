<?php

use App\Http\Middleware\EnsureEmpresaConfigurada;
use App\Http\Middleware\AuthenticateIntegrationToken;
use App\Http\Middleware\EnsureIntegrationPermission;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        App\Console\Commands\ImportLegacyProductionData::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state', 'XSRF-TOKEN']);
        $middleware->alias([
            'empresa.configurada' => EnsureEmpresaConfigurada::class,
            'integration.auth' => AuthenticateIntegrationToken::class,
            'integration.permission' => EnsureIntegrationPermission::class,
            'permission' => EnsurePermission::class,
        ]);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
