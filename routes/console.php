<?php

use App\Models\Device;
use App\Models\SystemMetric;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

Artisan::command('user:create', function () {
    $name = text('Naam', required: true);
    $email = text('E-mailadres', required: true, validate: fn ($v) => Validator::make(['e' => $v], ['e' => 'email|unique:users,email'])->fails()
        ? 'Ongeldig of al in gebruik.' : null);
    $password = password('Wachtwoord (min. 8 tekens)', required: true, validate: fn ($v) => strlen($v) < 8 ? 'Minimaal 8 tekens.' : null);

    User::create(compact('name', 'email', 'password'));

    $this->info("Account voor {$email} aangemaakt. Je kunt nu inloggen.");
})->purpose('Maak een account aan om in te loggen op het dashboard');

Artisan::command('device:create {name?} {type?}', function (?string $name = null, ?string $type = null) {
    $name ??= text('Naam van het apparaat', required: true);
    $type ??= select('Type', Device::TYPES);

    [$device, $token] = Device::createWithToken($name, $type);

    $this->info("Apparaat \"{$device->name}\" aangemaakt.");
    $this->line("Token (wordt maar één keer getoond): <comment>{$token}</comment>");
    $this->line('Server-URL: '.url('/api/report'));
})->purpose('Voeg een apparaat toe en toon het token voor de agent');

Schedule::command('model:prune', ['--model' => [SystemMetric::class]])->daily();
