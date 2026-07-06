<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

function grantPermissions(\App\Models\User $user, string|array $permissions): \App\Models\User
{
    $role = \App\Models\Role::query()->create([
        'nombre' => 'Rol de prueba '.\Illuminate\Support\Str::random(6),
        'slug' => 'rol-prueba-'.\Illuminate\Support\Str::random(8),
        'descripcion' => 'Rol generado para pruebas automatizadas.',
        'estado' => true,
    ]);

    foreach ((array) $permissions as $permissionSlug) {
        $permission = \App\Models\Permission::query()->firstOrCreate(
            ['slug' => $permissionSlug],
            [
                'nombre' => $permissionSlug,
                'modulo' => explode('.', $permissionSlug)[0] ?? 'pruebas',
                'descripcion' => 'Permiso generado para pruebas automatizadas.',
                'estado' => true,
            ],
        );

        $role->permissions()->syncWithoutDetaching([$permission->id]);
    }

    $user->roles()->syncWithoutDetaching([$role->id]);
    $user->unsetRelation('roles');

    return $user;
}
