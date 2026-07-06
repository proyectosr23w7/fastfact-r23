<?php

use App\Models\Configuracion\Empresa;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    Empresa::query()->create([
        'nombre_empresa' => 'Empresa Demo',
        'razon_social' => 'Empresa Demo S.R.L.',
        'nit' => '123456789',
        'propietario' => 'Demo',
        'direccion' => 'Av. Demo',
        'telefono' => '70000000',
        'correo' => 'demo@example.test',
        'estado' => true,
    ]);
    $user = grantPermissions(User::factory()->create(), 'sistema.access');
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertStatus(200);
});
