<?php

use App\Enums\RolSistemaEnum;
use App\Models\Configuracion\PuntoVenta;
use App\Models\Configuracion\Sucursal;
use App\Models\Cufd;
use App\Models\Cuis;
use App\Models\IntegrationApiToken;
use App\Models\Role;
use App\Models\User;

function createIntegrationContext(User $user): array
{
    $sucursal = Sucursal::query()->create([
        'codigo' => 1,
        'nombre' => 'Sucursal API',
        'estado' => true,
    ]);
    $puntoVenta = PuntoVenta::query()->create([
        'sucursal_id' => $sucursal->id,
        'codigo' => 0,
        'nombre' => 'Punto API',
        'estado' => true,
    ]);

    return [$sucursal, $puntoVenta];
}

function issueIntegrationToken(User $user, array $abilities): string
{
    [, $plainTextToken] = IntegrationApiToken::issueFor($user, 'Prueba', $abilities);

    return $plainTextToken;
}

test('integration endpoints require a bearer token', function () {
    $this->getJson('/api/integracion/cufd/actual')
        ->assertUnauthorized();
});

test('integration endpoints reject superadmin without administrator role', function () {
    $user = User::factory()->create();
    $role = Role::query()->where('slug', RolSistemaEnum::SUPERADMIN->value)->firstOrFail();
    $user->roles()->attach($role);
    [$sucursal, $puntoVenta] = createIntegrationContext($user);
    $plainTextToken = issueIntegrationToken($user, ['integracion.cufd.manage']);

    $this->withToken($plainTextToken)
        ->getJson("/api/integracion/cufd/actual?sucursal_id={$sucursal->id}&punto_venta_id={$puntoVenta->id}")
        ->assertForbidden()
        ->assertJsonPath('message', 'La API de integracion solo acepta usuarios administradores.');
});

test('integration endpoints allow administrator with explicit integration permission', function () {
    $user = User::factory()->create();
    $role = Role::query()->where('slug', RolSistemaEnum::ADMINISTRADOR->value)->firstOrFail();
    $user->roles()->attach($role);
    [$sucursal, $puntoVenta] = createIntegrationContext($user);
    Cufd::query()->create([
        'codigo' => 'CUFD-TEST',
        'codigo_control' => 'CTRL-TEST',
        'sucursal_id' => $sucursal->id,
        'punto_venta_id' => $puntoVenta->id,
        'ambiente_facturacion' => 'piloto',
        'fecha_vigencia' => now()->addDay(),
        'estado' => true,
        'user_id' => $user->id,
    ]);
    $plainTextToken = issueIntegrationToken($user, ['integracion.cufd.manage']);

    $this->withToken($plainTextToken)
        ->getJson("/api/integracion/cufd/actual?sucursal_id={$sucursal->id}&punto_venta_id={$puntoVenta->id}")
        ->assertOk()
        ->assertJsonPath('data.codigo', 'CUFD-TEST');
});

test('integration API exposes current CUIS with explicit permission', function () {
    $user = User::factory()->create();
    $role = Role::query()->where('slug', RolSistemaEnum::ADMINISTRADOR->value)->firstOrFail();
    $user->roles()->attach($role);
    [$sucursal, $puntoVenta] = createIntegrationContext($user);
    Cuis::query()->create([
        'codigo' => 'CUIS-TEST',
        'sucursal_id' => $sucursal->id,
        'punto_venta_id' => $puntoVenta->id,
        'ambiente_facturacion' => 'piloto',
        'fecha_vigencia' => now()->addDay(),
        'estado' => true,
        'user_id' => $user->id,
    ]);
    $plainTextToken = issueIntegrationToken($user, ['integracion.cuis.manage']);

    $this->withToken($plainTextToken)
        ->getJson('/api/integracion/cuis/actual?sucursal_id='.$sucursal->id.'&punto_venta_id='.$puntoVenta->id)
        ->assertOk()
        ->assertJsonPath('data.codigo', 'CUIS-TEST');
});

test('integration endpoints require token ability too', function () {
    $user = User::factory()->create();
    $role = Role::query()->where('slug', RolSistemaEnum::ADMINISTRADOR->value)->firstOrFail();
    $user->roles()->attach($role);
    [$sucursal, $puntoVenta] = createIntegrationContext($user);
    $plainTextToken = issueIntegrationToken($user, ['integracion.clientes.manage']);

    $this->withToken($plainTextToken)
        ->getJson("/api/integracion/cufd/actual?sucursal_id={$sucursal->id}&punto_venta_id={$puntoVenta->id}")
        ->assertForbidden()
        ->assertJsonPath('message', 'El token no tiene permiso para esta operacion de integracion.');
});
