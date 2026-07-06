<?php

use App\Models\Configuracion\Empresa;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->user = grantPermissions(User::factory()->create(), 'configuracion.empresa.view');
    $this->empresa = Empresa::query()->create([
        'nombre_empresa' => 'Comercial Andina',
        'razon_social' => 'Comercial Andina S.R.L.',
        'nit' => '1023457024',
        'propietario' => 'Juana Pérez',
        'direccion' => 'Av. 16 de Julio 1234',
        'telefono' => '22452333',
        'correo' => 'ventas@andina.test',
        'estado' => true,
    ]);
});

function empresaPayload(array $overrides = []): array
{
    return [
        'nombre_empresa' => 'Comercial Andina Actualizada',
        'razon_social' => 'Comercial Andina S.R.L.',
        'nit' => '1023457024',
        'propietario' => 'Juana Pérez',
        'direccion' => 'Av. 16 de Julio 1234, La Paz',
        'telefono' => '22452333',
        'correo' => 'contacto@andina.test',
        'estado' => true,
        ...$overrides,
    ];
}

it('updates the singleton company profile without requiring a logo', function () {
    $response = $this->actingAs($this->user)
        ->putJson("/api/configuracion/empresa/{$this->empresa->id}", empresaPayload());

    $response
        ->assertOk()
        ->assertJsonPath('data.nombre_empresa', 'Comercial Andina Actualizada')
        ->assertJsonPath('data.direccion', 'Av. 16 de Julio 1234, La Paz')
        ->assertJsonPath('data.logo_configurado', false)
        ->assertJsonPath('message', 'Empresa actualizada correctamente.');

    expect(Empresa::query()->count())->toBe(1);
});

it('stores and serves a validated company logo', function () {
    $logo = UploadedFile::fake()->image('logo.png', 300, 300)->size(450);

    $response = $this->actingAs($this->user)->post(
        "/api/configuracion/empresa/{$this->empresa->id}",
        empresaPayload(['logo' => $logo, '_method' => 'PUT']),
        ['Accept' => 'application/json'],
    );

    $response
        ->assertOk()
        ->assertJsonPath('data.logo_configurado', true);

    $path = (string) $this->empresa->fresh()->logo;
    Storage::disk('public')->assertExists($path);

    $this->actingAs($this->user)
        ->get("/api/configuracion/empresa/{$this->empresa->id}/logo")
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('replaces the previous managed logo', function () {
    Storage::disk('public')->put('empresas/logos/anterior.png', 'old-logo');
    $this->empresa->update(['logo' => 'empresas/logos/anterior.png']);

    $this->actingAs($this->user)->post(
        "/api/configuracion/empresa/{$this->empresa->id}",
        empresaPayload([
            'logo' => UploadedFile::fake()->image('nuevo.webp', 300, 300)->size(400),
            '_method' => 'PUT',
        ]),
        ['Accept' => 'application/json'],
    )->assertOk();

    Storage::disk('public')->assertMissing('empresas/logos/anterior.png');
    Storage::disk('public')->assertExists((string) $this->empresa->fresh()->logo);
});

it('rejects unsupported or oversized logo files', function () {
    $this->actingAs($this->user)->post(
        "/api/configuracion/empresa/{$this->empresa->id}",
        empresaPayload([
            'logo' => UploadedFile::fake()->create('logo.svg', 100, 'image/svg+xml'),
            '_method' => 'PUT',
        ]),
        ['Accept' => 'application/json'],
    )->assertUnprocessable()->assertJsonValidationErrors('logo');

    $this->actingAs($this->user)->post(
        "/api/configuracion/empresa/{$this->empresa->id}",
        empresaPayload([
            'logo' => UploadedFile::fake()->image('logo-grande.jpg')->size(2100),
            '_method' => 'PUT',
        ]),
        ['Accept' => 'application/json'],
    )->assertUnprocessable()->assertJsonValidationErrors('logo');
});
