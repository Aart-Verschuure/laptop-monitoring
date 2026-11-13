@extends('layouts.app', ['title' => 'Inloggen · '.$device->name])

@section('content')
    <h1 class="mb-4 text-2xl font-semibold">🔐 Inloggen per dag</h1>

    @if ($device->isPhone())
        <div class="mb-6 rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-600">
            Android laat het aantal keer ontgrendelen niet uitlezen zonder root-rechten. Daarom blijft deze pagina leeg voor een telefoon,
            tenzij je ontgrendelingen zelf doorstuurt, bijvoorbeeld via een automatisering in Tasker of MacroDroid (zie <code>agents/README.md</code>).
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-5">
        <section class="rounded-xl bg-white shadow-sm lg:col-span-3">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3">
                <h2 class="font-semibold">Laatste 30 dagen</h2>
                <div class="flex gap-3 text-xs text-slate-500">
                    <span><span class="inline-block h-2 w-2 rounded-sm bg-indigo-500"></span> gelukt</span>
                    <span><span class="inline-block h-2 w-2 rounded-sm bg-red-500"></span> mislukt</span>
                </div>
            </div>
            <ul class="divide-y divide-slate-100 text-sm">
                @foreach ($perDay as $day)
                    <li>
                        <a href="{{ route('devices.logins', [$device, 'date' => $day['date']->toDateString()]) }}"
                           @class(['flex items-center gap-3 px-5 py-1.5 hover:bg-slate-50', 'bg-indigo-50' => $day['date']->isSameDay($selected)])>
                            <span class="w-28 whitespace-nowrap text-slate-500">{{ $day['date']->translatedFormat('D d M') }}</span>
                            <span class="flex h-3 flex-1 overflow-hidden rounded-full bg-slate-100">
                                <span class="h-full bg-indigo-500" style="width: {{ $day['success'] / $max * 100 }}%"></span>
                                <span class="h-full bg-red-500" style="width: {{ $day['failed'] / $max * 100 }}%"></span>
                            </span>
                            <span class="w-20 text-right tabular-nums">
                                <strong>{{ $day['success'] }}</strong>
                                @if ($day['failed'])<span class="text-red-600">+{{ $day['failed'] }}</span>@endif
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>

        <section class="rounded-xl bg-white shadow-sm lg:col-span-2">
            <div class="border-b border-slate-100 px-5 py-3">
                <h2 class="font-semibold">{{ ucfirst($selected->translatedFormat('l j F Y')) }}</h2>
                <p class="text-xs text-slate-500">
                    {{ $dayEvents->whereIn('type', \App\Models\LoginEvent::SUCCESS_TYPES)->count() }} keer ingelogd,
                    {{ $dayEvents->where('type', 'failed')->count() }} mislukte pogingen
                </p>
            </div>
            <ul class="divide-y divide-slate-100 text-sm">
                @forelse ($dayEvents as $event)
                    <li class="flex items-center justify-between gap-3 px-5 py-2">
                        <span class="tabular-nums text-slate-500">{{ $event->occurred_at->format('H:i:s') }}</span>
                        <span @class([
                            'rounded-full px-2 py-0.5 text-xs font-medium',
                            'bg-red-100 text-red-700' => $event->type === 'failed',
                            'bg-indigo-100 text-indigo-700' => $event->type !== 'failed',
                        ])>{{ $event->label() }}</span>
                        <span class="flex-1 truncate text-right text-slate-600">{{ $event->username }}</span>
                    </li>
                @empty
                    <li class="px-5 py-6 text-center text-slate-500">Geen inlogmomenten op deze dag.</li>
                @endforelse
            </ul>
        </section>
    </div>
@endsection
