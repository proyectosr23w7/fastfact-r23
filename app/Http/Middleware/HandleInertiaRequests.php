<?php

namespace App\Http\Middleware;

use App\Enums\TipoFacturacionEnum;
use App\Http\Resources\RolResource;
use App\Http\Resources\UsuarioResource;
use App\Models\Configuracion\Configuracion;
use App\Models\Configuracion\Empresa;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');
        $user = $request->user()?->loadMissing(['roles.permissions']);
        $configuracion = Configuracion::current();
        $empresa = Empresa::query()->orderByDesc('estado')->orderBy('id')->first();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $user ? UsuarioResource::make($user)->resolve() : null,
                'roles' => $user ? RolResource::collection($user->roles)->resolve() : [],
                'permissions' => $user?->permission_slugs ?? [],
            ],
            'appFlags' => [
                'empresaConfigurada' => Empresa::query()->exists(),
                'facturacionActiva' => (bool) ($configuracion?->facturacionSiatActiva() ?? false),
                'facturacionElectronicaActiva' => (bool) (($configuracion?->facturacionSiatActiva() ?? false) && (int) ($configuracion?->tipo_facturacion ?? 0) === TipoFacturacionEnum::ELECTRONICA->value),
            ],
            'appContext' => [
                'empresa' => $empresa?->nombre_empresa ?: $empresa?->razon_social ?: 'Empresa sin nombre',
                'sucursal' => 'Contexto general',
                'puntoVenta' => null,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
