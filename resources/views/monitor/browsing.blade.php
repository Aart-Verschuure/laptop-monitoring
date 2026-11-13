@extends('layouts.app', ['title' => 'Browsen · '.$device->name])

@section('content')
    <h1 class="mb-4 text-2xl font-semibold">🌐 Browsegeschiedenis</h1>

    @if ($device->isPhone())
        <div class="mb-6 rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-600">
            Android-browsers geven hun geschiedenis niet rechtstreeks vrij aan andere apps, en van incognito-tabbladen wordt niets opgeslagen.
            Gebruik je Chrome op de telefoon met hetzelfde Google-account als op de laptop? Dan kan het via synchronisatie:
            vul in de browser-extensie op de laptop het <strong>telefoon-token</strong> in bij "Gesynchroniseerde apparaten",
            of importeer hieronder je geschiedenis uit Google Takeout.
        </div>
    @endif

    <div class="mb-6 grid gap-4 lg:grid-cols-4">
        <form method="GET" class="flex flex-wrap items-end gap-3 rounded-xl bg-white p-4 shadow-sm lg:col-span-3">
            <div class="min-w-48 flex-1">
                <label class="mb-1 block text-xs font-medium text-slate-500">Zoeken (URL of titel)</label>
                <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500">Soort</label>
                <select name="mode" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="all" @selected(($filters['mode'] ?? 'all') === 'all')>Alles</option>
                    <option value="incognito" @selected(($filters['mode'] ?? '') === 'incognito')>Alleen incognito</option>
                    <option value="normal" @selected(($filters['mode'] ?? '') === 'normal')>Alleen normaal</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500">Datum</label>
                <input type="date" name="date" value="{{ $filters['date'] ?? '' }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Filter</button>
            <a href="{{ route('devices.browsing', $device) }}" class="px-2 py-2 text-sm text-slate-500 hover:underline">Wissen</a>
        </form>

        <div class="rounded-xl bg-white p-4 shadow-sm">
            <div class="mb-2 text-xs font-medium uppercase text-slate-500">Vaakst bezocht vandaag</div>
            <ul class="space-y-1 text-sm">
                @forelse ($topDomains as $domain)
                    <li class="flex justify-between gap-2"><span class="truncate">{{ $domain->domain }}</span><span class="text-slate-500">{{ $domain->visits }}×</span></li>
                @empty
                    <li class="text-slate-500">Nog niets vandaag.</li>
                @endforelse
            </ul>
            <div class="mt-3 border-t border-slate-100 pt-2 text-xs text-slate-500">🕶️ {{ $incognitoToday }} incognito-bezoeken vandaag</div>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-2">Tijd</th>
                    <th class="px-4 py-2">Pagina</th>
                    <th class="px-4 py-2">Browser</th>
                    <th class="px-4 py-2">Modus</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($visits as $visit)
                    <tr @class(['bg-violet-50' => $visit->incognito])>
                        <td class="whitespace-nowrap px-4 py-2 text-slate-500">{{ $visit->visited_at->format('d-m-Y H:i:s') }}</td>
                        <td class="max-w-xl px-4 py-2">
                            <div class="truncate font-medium">{{ $visit->title ?: $visit->domain }}</div>
                            <a href="{{ $visit->url }}" target="_blank" rel="noopener noreferrer" class="block truncate text-xs text-indigo-600 hover:underline">{{ $visit->url }}</a>
                        </td>
                        <td class="px-4 py-2 text-slate-500">{{ $visit->browser }}</td>
                        <td class="whitespace-nowrap px-4 py-2">
                            @if ($visit->incognito)
                                <span class="rounded-full bg-violet-200 px-2 py-0.5 text-xs font-medium text-violet-800">🕶️ Incognito</span>
                            @else
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">Normaal</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">Geen bezoeken gevonden.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $visits->links() }}</div>

    <details class="mt-6 rounded-xl bg-white p-4 shadow-sm" @if ($errors->has('file')) open @endif>
        <summary class="cursor-pointer text-sm font-semibold">🔄 Geschiedenis van je Google-account importeren</summary>
        <div class="mt-3 space-y-3 text-sm text-slate-600">
            <ol class="list-decimal space-y-1 pl-5">
                <li>Ga naar <a href="https://takeout.google.com" target="_blank" rel="noopener noreferrer" class="text-indigo-600 hover:underline">takeout.google.com</a>, klik op <em>Alles deselecteren</em> en vink alleen <strong>Chrome</strong> aan.</li>
                <li>Download het archief en pak het uit. Kies het bestand <code>Chrome/History.json</code> (of <code>BrowserHistory.json</code>).</li>
                <li>Upload het hier. Bezoeken die al eerder zijn geïmporteerd worden overgeslagen, dus je kunt dit later opnieuw doen.</li>
            </ol>
            <p class="text-xs text-slate-500">Hierin staan ook bezoeken van andere apparaten met hetzelfde Google-account, mits geschiedenis-synchronisatie aan staat. Incognito zit er nooit in.</p>
            <form method="POST" action="{{ route('devices.browsing.import', $device) }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-3">
                @csrf
                <input type="file" name="file" accept=".json,application/json" required class="text-sm">
                <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Importeren</button>
            </form>
            @error('file')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    </details>
@endsection
