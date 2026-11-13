@extends('layouts.app', ['title' => 'Systeem · '.$device->name, 'refresh' => 60])

@use('App\Support\Format')

@section('content')
    <h1 class="mb-4 text-2xl font-semibold">🖥️ Systeem</h1>

    @include('monitor.partials.no-data')

    <div class="mb-6 grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-5">
        <x-stat label="Aan sinds" :value="Format::duration($metric?->uptimeSeconds())"
                :sub="$metric?->booted_at ? 'opgestart '.$metric->booted_at->format('d-m-Y H:i') : null" />
        <x-stat label="CPU" :value="$metric?->cpu_percent !== null ? round($metric->cpu_percent).'%' : '—'"
                :percent="$metric?->cpu_percent"
                :sub="$metric && $metric->cpu_percent === null && $device->isPhone() ? 'niet uitleesbaar op Android' : null" />
        <x-stat label="Geheugen" :value="$metric?->memoryPercent() !== null ? $metric->memoryPercent().'%' : '—'"
                :percent="$metric?->memoryPercent()"
                :sub="$metric ? Format::mb($metric->memory_used_mb).' van '.Format::mb($metric->memory_total_mb) : null" />
        <x-stat label="Opslag" :value="$metric?->diskPercent() !== null ? $metric->diskPercent().'%' : '—'"
                :percent="$metric?->diskPercent()"
                :sub="$metric?->disk_total_gb ? number_format($metric->disk_used_gb, 1, ',', '.').' van '.number_format($metric->disk_total_gb, 1, ',', '.').' GB' : null" />
        <x-stat label="Batterij" :value="$metric?->battery_percent !== null ? $metric->battery_percent.'%' : '—'"
                :percent="$metric?->battery_percent !== null ? 100 - $metric->battery_percent : null"
                :sub="collect([
                    $metric?->charging === null ? null : ($metric->charging ? '🔌 laadt' : '🔋 op batterij'),
                    $metric?->battery_temperature !== null ? $metric->battery_temperature.' °C' : null,
                ])->filter()->implode(' · ') ?: null" />
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-xl bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-3">
                <h2 class="font-semibold">{{ $device->isPhone() ? 'Telefoonspecificaties' : 'Specificaties' }}</h2>
            </div>
            @if ($specs)
                <dl class="divide-y divide-slate-100 text-sm">
                    @foreach ($specs as $key => $value)
                        <div class="flex justify-between gap-6 px-5 py-2">
                            <dt class="text-slate-500">{{ Format::specLabel($key) }}</dt>
                            <dd class="text-right font-medium break-all">
                                @if (is_bool($value))
                                    {{ $value ? 'Ja' : 'Nee' }}
                                @elseif (is_array($value))
                                    {{ implode(', ', array_map(fn ($v) => is_scalar($v) ? $v : json_encode($v), $value)) }}
                                @else
                                    {{ $value }}
                                @endif
                            </dd>
                        </div>
                    @endforeach
                </dl>
            @else
                <p class="px-5 py-6 text-sm text-slate-500">Nog geen specificaties ontvangen.</p>
            @endif
        </section>

        <section class="rounded-xl bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-3">
                <h2 class="font-semibold">Gemiddeld per uur (laatste 24 uur)</h2>
            </div>
            <table class="w-full text-sm">
                <thead class="text-left text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-5 py-2">Uur</th>
                        <th class="px-5 py-2">CPU</th>
                        <th class="px-5 py-2">Geheugen</th>
                        <th class="px-5 py-2">Batterij</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($hourly->reverse() as $row)
                        <tr>
                            <td class="px-5 py-1.5 text-slate-500">{{ $row['hour'] }}</td>
                            @foreach (['cpu' => 'bg-indigo-500', 'memory' => 'bg-sky-500', 'battery' => 'bg-emerald-500'] as $key => $color)
                                <td class="px-5 py-1.5">
                                    @if ($row[$key] !== null)
                                        <div class="flex items-center gap-2">
                                            <div class="h-1.5 w-20 overflow-hidden rounded-full bg-slate-100">
                                                <div class="h-full {{ $color }}" style="width: {{ round($row[$key]) }}%"></div>
                                            </div>
                                            <span class="w-10 text-right tabular-nums">{{ round($row[$key]) }}%</span>
                                        </div>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-5 py-6 text-center text-slate-500">Nog geen metingen.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    </div>
@endsection
