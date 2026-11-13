<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    @isset($refresh)<meta http-equiv="refresh" content="{{ $refresh }}">@endisset
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>
<body class="min-h-screen bg-slate-100 text-slate-800 antialiased">
@auth
    <header class="bg-slate-900 text-slate-100">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center gap-4 px-4 py-3">
            <a href="{{ route('home') }}" class="font-semibold tracking-tight">💻 Laptop Monitoring</a>

            <nav class="flex flex-wrap gap-1 text-sm">
                @foreach ($navDevices as $navDevice)
                    <a href="{{ route('devices.dashboard', $navDevice) }}"
                       @class([
                           'rounded px-3 py-1.5 hover:bg-slate-700',
                           'bg-slate-700' => isset($device) && $device->is($navDevice),
                       ])>
                        {{ $navDevice->isPhone() ? '📱' : '💻' }} {{ $navDevice->name }}
                        <span @class(['ml-1 inline-block h-2 w-2 rounded-full', 'bg-emerald-400' => $navDevice->isOnline(), 'bg-slate-500' => ! $navDevice->isOnline()])></span>
                    </a>
                @endforeach
            </nav>

            <div class="ml-auto flex items-center gap-3 text-sm">
                <a href="{{ route('devices.index') }}" class="rounded px-3 py-1.5 hover:bg-slate-700">⚙️ Apparaten</a>
                <span class="text-slate-400">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="rounded bg-slate-700 px-3 py-1.5 hover:bg-slate-600">Uitloggen</button>
                </form>
            </div>
        </div>
    </header>

    @isset($device)
        @php($tabs = [
            'devices.dashboard' => '📊 Dashboard',
            'devices.browsing' => '🌐 Browsen',
            'devices.system' => '🖥️ Systeem',
            'devices.logins' => '🔐 Inloggen',
        ])
        <div class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-7xl gap-1 overflow-x-auto px-4">
                @foreach ($tabs as $routeName => $label)
                    <a href="{{ route($routeName, $device) }}"
                       @class([
                           'whitespace-nowrap border-b-2 px-4 py-3 text-sm font-medium',
                           'border-indigo-600 text-indigo-700' => request()->routeIs($routeName),
                           'border-transparent text-slate-600 hover:text-slate-900' => ! request()->routeIs($routeName),
                       ])>{{ $label }}</a>
                @endforeach
                <div class="ml-auto flex items-center whitespace-nowrap pl-4 text-xs text-slate-500">
                    @if ($device->last_seen_at)
                        Laatste update: {{ $device->last_seen_at->diffForHumans() }}
                    @else
                        Nog geen gegevens ontvangen
                    @endif
                </div>
            </div>
        </div>
    @endisset
@endauth

<main class="mx-auto max-w-7xl px-4 py-6">
    @if (session('status'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    @yield('content')
</main>
</body>
</html>
