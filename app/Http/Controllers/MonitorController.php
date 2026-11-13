<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\LoginEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class MonitorController extends Controller
{
    /** Algemeen dashboard: wat staat er open en hoelang al. */
    public function dashboard(Device $device): View
    {
        $openApps = $device->runningApps()
            ->whereNull('ended_at')
            ->orderBy('started_at')
            ->get();

        $closedToday = $device->runningApps()
            ->whereNotNull('ended_at')
            ->where('ended_at', '>=', today())
            ->orderByDesc('ended_at')
            ->limit(15)
            ->get();

        return view('monitor.dashboard', [
            'device' => $device,
            'openApps' => $openApps,
            'closedToday' => $closedToday,
            'metric' => $device->latestMetric,
            'loginsToday' => $device->loginEvents()
                ->whereIn('type', LoginEvent::SUCCESS_TYPES)
                ->where('occurred_at', '>=', today())
                ->count(),
            'visitsToday' => $device->browsingVisits()->where('visited_at', '>=', today())->count(),
        ]);
    }

    /** Alle bezochte websites, inclusief incognito. */
    public function browsing(Request $request, Device $device): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'mode' => ['nullable', 'in:all,incognito,normal'],
            'date' => ['nullable', 'date'],
        ]);

        $query = $device->browsingVisits()->latest('visited_at');

        if (! empty($filters['q'])) {
            $q = '%'.$filters['q'].'%';
            $query->where(fn ($w) => $w->where('url', 'like', $q)->orWhere('title', 'like', $q));
        }

        match ($filters['mode'] ?? 'all') {
            'incognito' => $query->where('incognito', true),
            'normal' => $query->where('incognito', false),
            default => null,
        };

        if (! empty($filters['date'])) {
            $day = Carbon::parse($filters['date']);
            $query->whereBetween('visited_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()]);
        }

        $topDomains = $device->browsingVisits()
            ->where('visited_at', '>=', today())
            ->whereNotNull('domain')
            ->selectRaw('domain, count(*) as visits')
            ->groupBy('domain')
            ->orderByDesc('visits')
            ->limit(8)
            ->get();

        return view('monitor.browsing', [
            'device' => $device,
            'visits' => $query->paginate(50)->withQueryString(),
            'topDomains' => $topDomains,
            'filters' => $filters,
            'incognitoToday' => $device->browsingVisits()->where('visited_at', '>=', today())->where('incognito', true)->count(),
        ]);
    }

    /** Technische gegevens: CPU, geheugen, schijf, batterij, uptime en specificaties. */
    public function system(Device $device): View
    {
        $metrics = $device->systemMetrics()
            ->where('recorded_at', '>=', now()->subDay())
            ->orderBy('recorded_at')
            ->get(['cpu_percent', 'memory_used_mb', 'memory_total_mb', 'battery_percent', 'recorded_at']);

        // Gemiddelde per uur over de laatste 24 uur.
        $hourly = $metrics
            ->groupBy(fn ($m) => $m->recorded_at->format('Y-m-d H:00'))
            ->map(fn ($group, $hour) => [
                'hour' => Carbon::parse($hour)->format('H:00'),
                'cpu' => $group->whereNotNull('cpu_percent')->avg('cpu_percent'),
                'memory' => $group->filter(fn ($m) => $m->memory_total_mb)->avg(fn ($m) => $m->memory_used_mb / $m->memory_total_mb * 100),
                'battery' => $group->whereNotNull('battery_percent')->avg('battery_percent'),
            ])
            ->values();

        return view('monitor.system', [
            'device' => $device,
            'metric' => $device->latestMetric,
            'hourly' => $hourly,
            'specs' => $device->specs ?? [],
        ]);
    }

    /** Hoe vaak er per dag is ingelogd. */
    public function logins(Request $request, Device $device): View
    {
        $selected = $request->filled('date')
            ? Carbon::parse($request->validate(['date' => ['date']])['date'])
            : today();

        $since = today()->subDays(29);

        $events = $device->loginEvents()
            ->where('occurred_at', '>=', $since)
            ->get(['type', 'occurred_at']);

        $perDay = collect(range(0, 29))->map(function ($i) use ($since, $events) {
            $day = $since->copy()->addDays($i);
            $ofDay = $events->filter(fn ($e) => $e->occurred_at->isSameDay($day));

            return [
                'date' => $day,
                'success' => $ofDay->whereIn('type', LoginEvent::SUCCESS_TYPES)->count(),
                'failed' => $ofDay->where('type', 'failed')->count(),
            ];
        })->reverse()->values();

        return view('monitor.logins', [
            'device' => $device,
            'perDay' => $perDay,
            'max' => max(1, $perDay->max(fn ($d) => $d['success'] + $d['failed'])),
            'selected' => $selected,
            'dayEvents' => $device->loginEvents()
                ->whereBetween('occurred_at', [$selected->copy()->startOfDay(), $selected->copy()->endOfDay()])
                ->orderByDesc('occurred_at')
                ->get(),
        ]);
    }
}
