<?php

use App\Models\IntegrationApiToken;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('integracion:token {email} {--name=Integracion externa} {--ability=* : Permiso permitido; puede repetirse}', function () {
    $user = User::query()->where('email', (string) $this->argument('email'))->first();

    if (! $user) {
        $this->error('No existe un usuario con ese email.');

        return self::FAILURE;
    }

    $abilities = (array) $this->option('ability');

    [$token, $plainTextToken] = IntegrationApiToken::issueFor(
        $user,
        (string) $this->option('name'),
        $abilities === [] ? ['*'] : $abilities,
    );

    $this->info('Token de integracion creado.');
    $this->line('ID: '.$token->id);
    $this->line('Usuario: '.$user->email);
    $this->line('Permisos: '.implode(', ', $token->abilities ?: ['*']));
    $this->warn('Copia este token ahora; no se volvera a mostrar:');
    $this->line($plainTextToken);

    return self::SUCCESS;
})->purpose('Crear un token Bearer para la API externa de integracion');
