<?php

use App\Enums\RolSistemaEnum;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('impide que un usuario desactive su propia cuenta', function () {
    $user = grantPermissions(User::factory()->create(['estado' => true]), 'seguridad.usuarios.manage');

    $this->actingAs($user)
        ->patchJson("/api/seguridad/usuarios/{$user->id}/estado", ['estado' => false])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('estado');

    expect($user->fresh()->estado)->toBeTrue();
});

it('guarda la asignacion de roles desde el acceso integrado', function () {
    $actor = grantPermissions(User::factory()->create(['estado' => true]), 'seguridad.usuarios.manage');
    $target = User::factory()->create(['estado' => true]);
    $role = Role::query()->create([
        'nombre' => 'Ventas',
        'slug' => 'ventas',
        'descripcion' => 'Acceso comercial.',
        'estado' => true,
    ]);

    $this->actingAs($actor)
        ->patchJson("/api/seguridad/usuarios/{$target->id}/acceso", [
            'role_ids' => [$role->id],
        ])
        ->assertOk();

    expect($target->fresh()->roles()->pluck('roles.id')->all())->toBe([$role->id]);
});

it('conserva el rol critico cuando falla una asignacion integrada', function () {
    $actor = grantPermissions(User::factory()->create(['estado' => true]), 'seguridad.usuarios.manage');
    $target = User::query()->firstOrCreate([
        'email' => 'proyectosr23w7@gmail.com',
    ], [
        'name' => 'TechDevR23W7',
        'password' => '12345678',
        'estado' => true,
    ]);
    $role = Role::query()->firstOrCreate([
        'slug' => RolSistemaEnum::SUPERADMIN->value,
    ], [
        'nombre' => 'Super Administrador',
        'descripcion' => 'Acceso total.',
        'estado' => true,
    ]);
    $target->roles()->syncWithoutDetaching([$role->id]);

    $this->actingAs($actor)
        ->patchJson("/api/seguridad/usuarios/{$target->id}/acceso", ['role_ids' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('role_ids');

    expect($target->fresh()->roles()->where('roles.id', $role->id)->exists())->toBeTrue();
});
