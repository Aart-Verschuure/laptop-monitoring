<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Eén endpoint waar alle agents hun gegevens naartoe sturen.
 * Elk onderdeel is optioneel; alleen wat meegestuurd wordt, wordt verwerkt.
 */
class ReportController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var Device $device */
        $device = $request->attributes->get('device');

        $data = $request->validate([
            'specs' => ['nullable', 'array'],

            'metrics' => ['nullable', 'array'],
            'metrics.cpu_percent' => ['nullable', 'numeric', 'between:0,100'],
            'metrics.memory_used_mb' => ['nullable', 'integer', 'min:0'],
            'metrics.memory_total_mb' => ['nullable', 'integer', 'min:0'],
            'metrics.disk_used_gb' => ['nullable', 'numeric', 'min:0'],
            'metrics.disk_total_gb' => ['nullable', 'numeric', 'min:0'],
            'metrics.battery_percent' => ['nullable', 'integer', 'between:0,100'],
            'metrics.charging' => ['nullable', 'boolean'],
            'metrics.battery_temperature' => ['nullable', 'numeric'],
            'metrics.booted_at' => ['nullable', 'date'],
            'metrics.recorded_at' => ['nullable', 'date'],

            'apps' => ['nullable', 'array', 'max:500'],
            'apps.*.name' => ['required', 'string', 'max:255'],
            'apps.*.title' => ['nullable', 'string'],
            'apps.*.pid' => ['nullable', 'integer'],
            'apps.*.started_at' => ['nullable', 'date'],

            'visits' => ['nullable', 'array', 'max:1000'],
            'visits.*.url' => ['required', 'string', 'max:4000'],
            'visits.*.title' => ['nullable', 'string'],
            'visits.*.browser' => ['nullable', 'string', 'max:50'],
            'visits.*.incognito' => ['nullable', 'boolean'],
            'visits.*.visited_at' => ['nullable', 'date'],
            'visits.*.source_id' => ['nullable', 'string', 'max:100'],

            'logins' => ['nullable', 'array', 'max:1000'],
            'logins.*.type' => ['required', 'in:logon,unlock,remote,failed'],
            'logins.*.username' => ['nullable', 'string', 'max:255'],
            'logins.*.source_id' => ['nullable', 'string', 'max:100'],
            'logins.*.occurred_at' => ['nullable', 'date'],
        ]);

        DB::transaction(function () use ($device, $data) {
            if (! empty($data['specs'])) {
                $device->specs = $data['specs'];
            }

            if (! empty($data['metrics'])) {
                $this->storeMetrics($device, $data['metrics']);
            }

            if (array_key_exists('apps', $data) && $data['apps'] !== null) {
                $this->syncApps($device, $data['apps']);
            }

            foreach ($data['visits'] ?? [] as $visit) {
                $attributes = [
                    'url' => $visit['url'],
                    'domain' => parse_url($visit['url'], PHP_URL_HOST) ?: null,
                    'title' => isset($visit['title']) ? Str::limit($visit['title'], 250) : null,
                    'browser' => $visit['browser'] ?? null,
                    'incognito' => (bool) ($visit['incognito'] ?? false),
                    'visited_at' => $this->time($visit['visited_at'] ?? null),
                ];

                // Imports en gesynchroniseerde tabbladen kunnen vaker binnenkomen: sla dubbele over.
                if (! empty($visit['source_id'])) {
                    $device->browsingVisits()->firstOrCreate(['source_id' => $visit['source_id']], $attributes);
                } else {
                    $device->browsingVisits()->create($attributes);
                }
            }

            foreach ($data['logins'] ?? [] as $login) {
                $attributes = [
                    'type' => $login['type'],
                    'username' => $login['username'] ?? null,
                    'occurred_at' => $this->time($login['occurred_at'] ?? null),
                ];

                if (! empty($login['source_id'])) {
                    $device->loginEvents()->firstOrCreate(['source_id' => $login['source_id']], $attributes);
                } else {
                    $device->loginEvents()->create($attributes);
                }
            }

            $device->last_seen_at = now();
            $device->save();
        });

        return response()->json(['ok' => true]);
    }

    private function storeMetrics(Device $device, array $m): void
    {
        $device->systemMetrics()->create([
            'cpu_percent' => $m['cpu_percent'] ?? null,
            'memory_used_mb' => $m['memory_used_mb'] ?? null,
            'memory_total_mb' => $m['memory_total_mb'] ?? null,
            'disk_used_gb' => $m['disk_used_gb'] ?? null,
            'disk_total_gb' => $m['disk_total_gb'] ?? null,
            'battery_percent' => $m['battery_percent'] ?? null,
            'charging' => $m['charging'] ?? null,
            'battery_temperature' => $m['battery_temperature'] ?? null,
            'booted_at' => isset($m['booted_at']) ? $this->time($m['booted_at']) : null,
            'recorded_at' => $this->time($m['recorded_at'] ?? null),
        ]);
    }

    /**
     * De agent stuurt telkens de complete lijst open programma's.
     * Nieuwe worden toegevoegd, bestaande bijgewerkt, verdwenen programma's afgesloten.
     */
    private function syncApps(Device $device, array $apps): void
    {
        $now = now();
        $open = $device->runningApps()->whereNull('ended_at')->get()
            ->keyBy(fn ($app) => $app->name.'|'.$app->pid);

        $seen = [];

        foreach ($apps as $app) {
            $key = $app['name'].'|'.($app['pid'] ?? '');
            $seen[$key] = true;

            if ($existing = $open->get($key)) {
                $existing->update([
                    'title' => isset($app['title']) ? Str::limit($app['title'], 250) : null,
                    'last_seen_at' => $now,
                ]);

                continue;
            }

            $device->runningApps()->create([
                'name' => $app['name'],
                'title' => isset($app['title']) ? Str::limit($app['title'], 250) : null,
                'pid' => $app['pid'] ?? null,
                'started_at' => $this->time($app['started_at'] ?? null),
                'last_seen_at' => $now,
            ]);
        }

        foreach ($open as $key => $app) {
            if (! isset($seen[$key])) {
                $app->update(['ended_at' => $app->last_seen_at]);
            }
        }
    }

    private function time(?string $value): Carbon
    {
        return $value
            ? Carbon::parse($value)->setTimezone(config('app.timezone'))
            : now();
    }
}
