<?php

use App\Models\Device;
use App\Models\SystemMetric;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
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

/*
 * Eenmalig: kopieer alle gegevens uit de lokale database naar de online database
 * (bv. Neon op Vercel). Lokale apparaten worden gekoppeld aan het online apparaat
 * van hetzelfde type. Kan veilig vaker draaien: wat er al staat, wordt overgeslagen.
 */
Artisan::command('data:upload', function () {
    $url = env('ONLINE_DB_URL');
    if (! $url) {
        $this->error('Zet eerst ONLINE_DB_URL op de postgresql://-URL van de online database.');

        return 1;
    }

    config(['database.connections.online' => array_merge(config('database.connections.pgsql'), ['url' => $url])]);
    $online = DB::connection('online');
    $local = DB::connection();
    $now = now()->toDateTimeString();

    foreach ($local->table('devices')->orderBy('id')->get() as $device) {
        $target = $online->table('devices')->where('type', $device->type)->orderBy('id')->first();
        if (! $target) {
            $this->warn("Geen online apparaat van type {$device->type} voor \"{$device->name}\", overgeslagen.");

            continue;
        }
        $this->info("\"{$device->name}\" -> online \"{$target->name}\"");

        $changes = array_filter([
            'specs' => $target->specs ?: $device->specs,
            'last_seen_at' => max((string) $target->last_seen_at, (string) $device->last_seen_at) ?: null,
        ], fn ($v) => $v !== null);
        if ($changes) {
            $online->table('devices')->where('id', $target->id)->update($changes);
        }

        // Bezoeken en inlogmomenten: dubbele voorkomen via source_id.
        foreach (['browsing_visits' => ['incognito'], 'login_events' => []] as $table => $bools) {
            $count = 0;
            $local->table($table)->where('device_id', $device->id)
                ->chunkById(500, function ($rows) use ($online, $table, $bools, $target, &$count) {
                    $insert = $rows->map(function ($row) use ($bools, $target) {
                        $row = (array) $row;
                        foreach ($bools as $b) {
                            $row[$b] = (bool) $row[$b];
                        }
                        $row['source_id'] ??= 'local:'.$row['id'];
                        $row['device_id'] = $target->id;
                        unset($row['id']);

                        return $row;
                    })->all();
                    $count += $online->table($table)->insertOrIgnore($insert);
                });
            $this->line("  {$table}: {$count} nieuw");
        }

        // Metingen en programma's hebben geen source_id: kopieer alleen wat ouder is
        // dan het oudste online record, zodat opnieuw draaien niets dubbel zet.
        foreach (['system_metrics' => ['charging'], 'running_apps' => []] as $table => $bools) {
            $oldest = $online->table($table)->where('device_id', $target->id)->min('created_at') ?? $now;
            $count = 0;
            $local->table($table)->where('device_id', $device->id)->where('created_at', '<', $oldest)
                ->chunkById(500, function ($rows) use ($online, $table, $bools, $target, &$count) {
                    $insert = $rows->map(function ($row) use ($bools, $target) {
                        $row = (array) $row;
                        foreach ($bools as $b) {
                            $row[$b] = $row[$b] === null ? null : (bool) $row[$b];
                        }
                        if (array_key_exists('ended_at', $row)) {
                            $row['ended_at'] ??= $row['last_seen_at']; // lokaal "open" is online al afgesloten
                        }
                        $row['device_id'] = $target->id;
                        unset($row['id']);

                        return $row;
                    })->all();
                    $online->table($table)->insert($insert);
                    $count += count($insert);
                });
            $this->line("  {$table}: {$count} nieuw");
        }
    }

    $this->info('Klaar.');
})->purpose('Kopieer de lokale gegevens naar de online database (ONLINE_DB_URL)');
