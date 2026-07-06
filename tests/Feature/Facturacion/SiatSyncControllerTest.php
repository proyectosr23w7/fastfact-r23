<?php

use App\Enums\RolSistemaEnum;
use App\Models\Configuracion\PuntoVenta;
use App\Models\Configuracion\Sucursal;
use App\Models\Role;
use App\Models\SiatSincronizacion;
use App\Models\SinMetodoPago;
use App\Models\SinUnidadMedida;
use App\Models\User;

function createSiatContext(int $codigoSucursal, int $codigoPunto): array
{
    $sucursal = Sucursal::query()->create([
        'codigo' => $codigoSucursal,
        'nombre' => "Sucursal {$codigoSucursal}",
        'estado' => true,
    ]);
    $punto = PuntoVenta::query()->create([
        'sucursal_id' => $sucursal->id,
        'codigo' => $codigoPunto,
        'nombre' => "Punto {$codigoPunto}",
        'estado' => true,
    ]);

    return [$sucursal, $punto];
}

function createSiatAdmin(): User
{
    $user = User::factory()->create();
    $role = Role::query()->where('slug', RolSistemaEnum::ADMINISTRADOR->value)->firstOrFail();
    $user->roles()->attach($role);

    return $user;
}

test('a user with SIAT permission receives synchronization history and active selectors', function () {
    createSiatContext(1, 10);
    createSiatContext(2, 20);
    $user = grantPermissions(User::factory()->create(), 'facturacion.siat.sync');

    SiatSincronizacion::query()->create([
        'codigo_sucursal' => 1,
        'codigo_punto_venta' => 10,
        'tipo_catalogo' => 'metodos_pago',
        'fecha_sincronizacion' => now(),
        'estado' => 'exitosa',
        'observacion' => 'Registro permitido',
        'user_id' => $user->id,
    ]);
    SiatSincronizacion::query()->create([
        'codigo_sucursal' => 2,
        'codigo_punto_venta' => 20,
        'tipo_catalogo' => 'monedas',
        'fecha_sincronizacion' => now(),
        'estado' => 'exitosa',
        'observacion' => 'Registro de otro contexto',
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->getJson('/api/facturacion/sincronizaciones/catalogos')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonCount(2, 'meta.sucursales')
        ->assertJsonCount(2, 'meta.puntos_venta')
        ->assertJsonPath('meta.puede_gestionar_catalogos', false)
        ->assertJsonPath('meta.puede_sincronizar', true);
});

test('only administrators can change operational catalog preferences', function () {
    $user = User::factory()->create();
    $method = SinMetodoPago::query()->create([
        'codigo_clasificador' => '1',
        'descripcion' => 'Efectivo',
        'estado' => true,
        'habilitado_venta' => true,
        'es_predeterminado' => true,
        'orden_operativo' => 1,
    ]);

    $this->actingAs($user)
        ->patchJson("/api/facturacion/catalogos/metodos-pago/{$method->id}/estado", ['estado' => false])
        ->assertForbidden();

    expect($method->refresh()->habilitado_venta)->toBeTrue();
});

test('setting a default payment method keeps a single enabled default', function () {
    $user = createSiatAdmin();
    $cash = SinMetodoPago::query()->create([
        'codigo_clasificador' => '1',
        'descripcion' => 'Efectivo',
        'estado' => true,
        'habilitado_venta' => true,
        'es_predeterminado' => true,
        'orden_operativo' => 1,
    ]);
    $card = SinMetodoPago::query()->create([
        'codigo_clasificador' => '2',
        'descripcion' => 'Tarjeta',
        'estado' => true,
        'habilitado_venta' => false,
        'es_predeterminado' => false,
        'orden_operativo' => 2,
    ]);

    $this->actingAs($user)
        ->patchJson("/api/facturacion/catalogos/metodos-pago/{$card->id}/operativo", ['es_predeterminado' => true])
        ->assertOk();

    expect($cash->refresh()->es_predeterminado)->toBeFalse()
        ->and($card->refresh()->es_predeterminado)->toBeTrue()
        ->and($card->habilitado_venta)->toBeTrue()
        ->and(SinMetodoPago::query()->where('es_predeterminado', true)->count())->toBe(1);
});

test('disabling a unit changes local availability without changing its SIAT state', function () {
    $user = createSiatAdmin();
    $unit = SinUnidadMedida::query()->create([
        'codigo_clasificador' => '58',
        'descripcion' => 'Unidad',
        'estado' => true,
        'habilitado_uso' => true,
    ]);

    $this->actingAs($user)
        ->patchJson("/api/facturacion/catalogos/unidades-medida/{$unit->id}/estado", ['estado' => false])
        ->assertOk();

    expect($unit->refresh()->estado)->toBeTrue()
        ->and($unit->habilitado_uso)->toBeFalse();
});
