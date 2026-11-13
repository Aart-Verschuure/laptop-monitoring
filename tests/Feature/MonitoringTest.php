<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        [$device] = Device::createWithToken('Laptop', 'laptop');

        $this->get('/')->assertRedirect('/login');
        $this->get(route('devices.dashboard', $device))->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('Inloggen');
    }

    public function test_user_can_log_in_and_out(): void
    {
        $user = User::factory()->create(['password' => 'geheim1234']);

        $this->post('/login', ['email' => $user->email, 'password' => 'fout'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/login', ['email' => $user->email, 'password' => 'geheim1234'])->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_api_requires_valid_device_token(): void
    {
        $this->postJson('/api/report', [])->assertUnauthorized();
        $this->postJson('/api/report', [], ['Authorization' => 'Bearer onzin'])->assertUnauthorized();
    }

    public function test_laptop_report_is_stored_and_shown_on_all_pages(): void
    {
        [$device, $token] = Device::createWithToken('Mijn laptop', 'laptop');
        $headers = ['Authorization' => "Bearer $token"];

        $this->postJson('/api/report', [
            'specs' => ['processor' => 'Intel Core i7', 'werkgeheugen' => '16 GB', 'videokaart' => ['Intel Iris Xe']],
            'metrics' => [
                'cpu_percent' => 23.5, 'memory_used_mb' => 8000, 'memory_total_mb' => 16000,
                'disk_used_gb' => 200, 'disk_total_gb' => 500, 'battery_percent' => 80, 'charging' => true,
                'booted_at' => now()->subHours(5)->toIso8601String(), 'recorded_at' => now()->toIso8601String(),
            ],
            'apps' => [
                ['name' => 'Google Chrome', 'title' => 'YouTube', 'pid' => 100, 'started_at' => now()->subMinutes(90)->toIso8601String()],
                ['name' => 'Visual Studio Code', 'title' => 'laptop_monitoring', 'pid' => 200, 'started_at' => now()->subMinutes(10)->toIso8601String()],
            ],
            'visits' => [
                ['url' => 'https://laravel.com/docs', 'title' => 'Laravel docs', 'browser' => 'Chrome', 'incognito' => false],
                ['url' => 'https://geheim.example.com/pagina', 'title' => 'Geheime pagina', 'browser' => 'Chrome', 'incognito' => true],
            ],
            'logins' => [
                ['type' => 'logon', 'username' => 'LAPTOP\\aart', 'source_id' => 'win-1', 'occurred_at' => now()->subHours(5)->toIso8601String()],
                ['type' => 'unlock', 'username' => 'LAPTOP\\aart', 'source_id' => 'win-2', 'occurred_at' => now()->subHour()->toIso8601String()],
                ['type' => 'failed', 'username' => 'LAPTOP\\aart', 'source_id' => 'win-3', 'occurred_at' => now()->subHour()->toIso8601String()],
            ],
        ], $headers)->assertOk();

        // Zelfde inlog-events opnieuw sturen mag geen dubbelingen geven
        $this->postJson('/api/report', [
            'logins' => [['type' => 'logon', 'source_id' => 'win-1', 'occurred_at' => now()->subHours(5)->toIso8601String()]],
        ], $headers)->assertOk();
        $this->assertSame(3, $device->loginEvents()->count());

        // Chrome is afgesloten, VS Code staat nog open
        $this->postJson('/api/report', [
            'apps' => [['name' => 'Visual Studio Code', 'title' => 'laptop_monitoring', 'pid' => 200]],
        ], $headers)->assertOk();
        $this->assertSame(1, $device->runningApps()->whereNull('ended_at')->count());
        $this->assertNotNull($device->runningApps()->where('name', 'Google Chrome')->first()->ended_at);

        $this->actingAs(User::factory()->create());

        $this->get('/')->assertRedirect(route('devices.dashboard', $device));

        $this->get(route('devices.dashboard', $device))->assertOk()
            ->assertSee('Visual Studio Code')
            ->assertSee('10m')
            ->assertSee('Google Chrome')     // bij "vandaag afgesloten"
            ->assertSee('5u 0m');            // uptime

        $this->get(route('devices.browsing', $device))->assertOk()
            ->assertSee('Laravel docs')
            ->assertSee('Geheime pagina')
            ->assertSee('Incognito');

        $this->get(route('devices.browsing', [$device, 'mode' => 'incognito']))->assertOk()
            ->assertSee('Geheime pagina')
            ->assertDontSee('Laravel docs');

        $this->get(route('devices.system', $device))->assertOk()
            ->assertSee('Intel Core i7')
            ->assertSee('Intel Iris Xe')
            ->assertSee('50%');

        $this->get(route('devices.logins', $device))->assertOk()
            ->assertSee('2 keer ingelogd')
            ->assertSee('1 mislukte pogingen');
    }

    public function test_phone_pages_show_specs(): void
    {
        [$phone, $token] = Device::createWithToken('Mijn telefoon', 'phone');

        $this->postJson('/api/report', [
            'specs' => ['merk' => 'samsung', 'model' => 'SM-S921B', 'android_versie' => '16', 'chipset' => 'Samsung Exynos 2400'],
            'metrics' => ['memory_used_mb' => 5000, 'memory_total_mb' => 7500, 'battery_percent' => 64, 'battery_temperature' => 31.2, 'charging' => false],
        ], ['Authorization' => "Bearer $token"])->assertOk();

        $this->actingAs(User::factory()->create());

        $this->get(route('devices.dashboard', $phone))->assertOk()->assertSee('SM-S921B')->assertSee('64%');
        $this->get(route('devices.system', $phone))->assertOk()->assertSee('Telefoonspecificaties')->assertSee('Exynos 2400')->assertSee('31.2 °C', false);
        $this->get(route('devices.browsing', $phone))->assertOk();
        $this->get(route('devices.logins', $phone))->assertOk();
    }

    public function test_synced_visits_with_source_id_are_not_duplicated(): void
    {
        [$phone, $token] = Device::createWithToken('Mijn telefoon', 'phone');
        $visit = [
            'url' => 'https://nos.nl/artikel/1', 'title' => 'NOS', 'browser' => 'Chrome · Pixel 9',
            'visited_at' => now()->subMinutes(3)->toIso8601String(), 'source_id' => 'sync:abc123',
        ];

        $this->postJson('/api/report', ['visits' => [$visit]], ['Authorization' => "Bearer $token"])->assertOk();
        $this->postJson('/api/report', ['visits' => [$visit]], ['Authorization' => "Bearer $token"])->assertOk();

        $this->assertSame(1, $phone->browsingVisits()->count());

        $this->actingAs(User::factory()->create());
        $this->get(route('devices.browsing', $phone))->assertOk()->assertSee('Chrome · Pixel 9');
    }

    public function test_google_takeout_history_can_be_imported(): void
    {
        [$device] = Device::createWithToken('Mijn laptop', 'laptop');
        $this->actingAs(User::factory()->create());

        $entries = [
            ['title' => 'Wikipedia', 'url' => 'https://nl.wikipedia.org/wiki/Laravel', 'time_usec' => 1763000000000000],
            ['title' => 'Instellingen', 'url' => 'chrome://settings', 'time_usec' => 1763000001000000],
            ['title' => 'YouTube', 'url' => 'https://www.youtube.com/', 'time_usec' => 1763000002000000],
        ];

        $this->postJson(route('devices.browsing.import', $device), ['entries' => $entries])
            ->assertOk()->assertJson(['imported' => 2, 'skipped' => 1]);

        $this->postJson(route('devices.browsing.import', $device), ['entries' => $entries])
            ->assertOk()->assertJson(['imported' => 0, 'skipped' => 3]);

        $this->assertSame(2, $device->browsingVisits()->count());
        $this->assertSame('nl.wikipedia.org', $device->browsingVisits()->oldest('visited_at')->first()->domain);

        $this->postJson(route('devices.browsing.import', $device), ['entries' => array_fill(0, 1001, $entries[0])])
            ->assertUnprocessable();

        $this->get(route('devices.browsing', $device))->assertOk()->assertSee('takeout-form', false);
    }

    public function test_devices_can_be_managed(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('devices.store'), ['name' => 'Werklaptop', 'type' => 'laptop'])
            ->assertRedirect(route('devices.index'))
            ->assertSessionHas('new_token');

        $device = Device::firstWhere('name', 'Werklaptop');
        $this->get(route('devices.index'))->assertOk()->assertSee('Werklaptop');

        $this->delete(route('devices.destroy', $device))->assertRedirect(route('devices.index'));
        $this->assertModelMissing($device);
    }
}
