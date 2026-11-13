@extends('layouts.app', ['title' => $device->name, 'refresh' => 60])

@use('App\Support\Format')

@section('content')
    <h1 class="mb-4 text-2xl font-semibold">{{ $device->isPhone() ? '📱' : '💻' }} {{ $device->name }}</h1>

    @include('monitor.partials.no-data')

    <div class="mb-6 grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-6">
        <x-stat label="Status" :value="$device->isOnline() ? '🟢 Online' : '⚪ Offline'"
                :sub="$device->last_seen_at?->diffForHumans()" />
        <x-stat label="Aan sinds" :value="Format::duration($metric?->uptimeSeconds())"
                :sub="$metric?->booted_at?->format('d-m H:i')" />
        <x-stat label="CPU" :value="$metric?->cpu_percent !== null ? round($metric->cpu_percent).'%' : '—'"
                :percent="$metric?->cpu_percent" />
        <x-stat label="Geheugen" :value="$metric?->memoryPercent() !== null ? $metric->memoryPercent().'%' : '—'"
                :percent="$metric?->memoryPercent()"
                :sub="$metric ? Format::mb($metric->memory_used_mb).' / '.Format::mb($metric->memory_total_mb) : null" />
        <x-stat label="Batterij" :value="$metric?->battery_percent !== null ? $metric->battery_percent.'%' : '—'"
                :sub="$metric?->charging === null ? null : ($metric->charging ? '🔌 Aan het laden' : '🔋 Op batterij')" />
        <x-stat label="Inlogs vandaag" :value="$loginsToday"
                :sub="$device->isPhone() ? null : $visitsToday.' websites bezocht'" />
    </div>

    @if ($device->isPhone())
        <div class="rounded-xl bg-white p-6 shadow-sm">
            <h2 class="mb-3 text-lg font-semibold">Telefoon</h2>
            @if ($device->specs)
                <dl class="grid grid-cols-1 gap-x-6 gap-y-2 text-sm sm:grid-cols-2 lg:grid-cols-3">
                    @foreach (array_slice($device->specs, 0, 9, true) as $key => $value)
                        <div class="flex justify-between gap-4 border-b border-slate-100 py-1">
                            <dt class="text-slate-500">{{ Format::specLabel($key) }}</dt>
                            <dd class="text-right font-medium">{{ is_array($value) ? json_encode($value) : $value }}</dd>
                        </div>
                    @endforeach
                </dl>
                <a href="{{ route('devices.system', $device) }}" class="mt-4 inline-block text-sm text-indigo-600 hover:underline">Alle specificaties →</a>
            @else
                <p class="text-sm text-slate-500">Nog geen specificaties ontvangen.</p>
            @endif
            <p class="mt-4 text-xs text-slate-500">
                Android laat apps zonder root-rechten niet zien welke andere apps er open zijn. Daarom toont de telefoon geen lijst met open apps.
            </p>
        </div>
    @else
        <div class="grid gap-6 lg:grid-cols-3">
            <section class="rounded-xl bg-white shadow-sm lg:col-span-2">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3">
                    <h2 class="font-semibold">Nu geopend ({{ $openApps->count() }})</h2>
                    <span class="text-xs text-slate-500">ververst elke minuut</span>
                </div>
                <table class="w-full text-sm">
                    <thead class="text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-5 py-2">Programma</th>
                            <th class="px-5 py-2">Venster</th>
                            <th class="px-5 py-2">Geopend om</th>
                            <th class="px-5 py-2 text-right">Open sinds</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($openApps as $app)
                            <tr>
                                <td class="px-5 py-2 font-medium">{{ $app->name }}</td>
                                <td class="max-w-xs truncate px-5 py-2 text-slate-500" title="{{ $app->title }}">{{ $app->title }}</td>
                                <td class="whitespace-nowrap px-5 py-2 text-slate-500">
                                    {{ $app->started_at->isToday() ? $app->started_at->format('H:i') : $app->started_at->format('d-m H:i') }}
                                </td>
                                <td class="whitespace-nowrap px-5 py-2 text-right font-semibold">{{ Format::duration($app->openSeconds()) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-6 text-center text-slate-500">Geen open programma's bekend.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>

            <section class="rounded-xl bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-3">
                    <h2 class="font-semibold">Vandaag afgesloten</h2>
                </div>
                <ul class="divide-y divide-slate-100 text-sm">
                    @forelse ($closedToday as $app)
                        <li class="flex justify-between gap-3 px-5 py-2">
                            <span class="truncate">{{ $app->name }}</span>
                            <span class="whitespace-nowrap text-slate-500">{{ Format::duration($app->openSeconds()) }} · tot {{ $app->ended_at->format('H:i') }}</span>
                        </li>
                    @empty
                        <li class="px-5 py-6 text-center text-slate-500">Nog niets afgesloten vandaag.</li>
                    @endforelse
                </ul>
            </section>
        </div>
    @endif
@endsection
