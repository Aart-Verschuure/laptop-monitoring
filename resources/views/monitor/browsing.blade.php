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

    <details class="mt-6 rounded-xl bg-white p-4 shadow-sm">
        <summary class="cursor-pointer text-sm font-semibold">🔄 Geschiedenis van je Google-account importeren</summary>
        <div class="mt-3 space-y-3 text-sm text-slate-600">
            <ol class="list-decimal space-y-1 pl-5">
                <li>Ga naar <a href="https://takeout.google.com" target="_blank" rel="noopener noreferrer" class="text-indigo-600 hover:underline">takeout.google.com</a>, klik op <em>Alles deselecteren</em> en vink alleen <strong>Chrome</strong> aan.</li>
                <li>Download het archief en pak het uit. Kies het bestand <code>Chrome/History.json</code> (of <code>BrowserHistory.json</code>).</li>
                <li>Upload het hier. Bezoeken die al eerder zijn geïmporteerd worden overgeslagen, dus je kunt dit later opnieuw doen.</li>
            </ol>
            <p class="text-xs text-slate-500">Hierin staan ook bezoeken van andere apparaten met hetzelfde Google-account, mits geschiedenis-synchronisatie aan staat. Incognito zit er nooit in.</p>
            <form id="takeout-form" class="flex flex-wrap items-center gap-3">
                <input id="takeout-file" type="file" accept=".json,application/json" required class="text-sm">
                <button id="takeout-button" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-60">Importeren</button>
            </form>
            <div id="takeout-progress" class="hidden">
                <div class="h-2 overflow-hidden rounded-full bg-slate-100"><div id="takeout-bar" class="h-full w-0 bg-indigo-600 transition-all"></div></div>
            </div>
            <p id="takeout-status" class="text-sm"></p>
        </div>
    </details>

    <script>
        // Leest het Takeout-bestand in de browser en stuurt het in stukjes naar de server,
        // zodat grote bestanden (tientallen MB's) niet tegen de PHP-uploadlimiet aanlopen.
        document.getElementById('takeout-form').addEventListener('submit', async (event) => {
            event.preventDefault();
            const file = document.getElementById('takeout-file').files[0];
            const button = document.getElementById('takeout-button');
            const status = document.getElementById('takeout-status');
            const bar = document.getElementById('takeout-bar');
            const setStatus = (text, error = false) => {
                status.textContent = text;
                status.className = 'text-sm ' + (error ? 'text-red-600' : 'text-slate-700');
            };
            if (!file) return;

            button.disabled = true;
            setStatus('Bestand lezen…');

            let entries;
            try {
                const json = JSON.parse(await file.text());
                entries = Array.isArray(json) ? json : json['Browser History'];
                if (!Array.isArray(entries)) throw new Error();
            } catch {
                setStatus('Dit is geen Chrome-geschiedenis uit Google Takeout (History.json of BrowserHistory.json).', true);
                button.disabled = false;
                return;
            }

            const chunk = {{ \App\Http\Controllers\BrowsingImportController::CHUNK }};
            let imported = 0, skipped = 0;
            document.getElementById('takeout-progress').classList.remove('hidden');

            try {
                for (let i = 0; i < entries.length; i += chunk) {
                    const part = entries.slice(i, i + chunk)
                        .map(e => ({ url: e.url, title: e.title, time_usec: e.time_usec }));
                    const res = await fetch(@json(route('devices.browsing.import', $device)), {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) },
                        body: JSON.stringify({ entries: part }),
                    });
                    if (!res.ok) throw new Error(res.status === 419 ? 'Sessie verlopen, herlaad de pagina.' : 'Server antwoordde met HTTP ' + res.status);
                    const r = await res.json();
                    imported += r.imported;
                    skipped += r.skipped;
                    const done = Math.min(i + chunk, entries.length);
                    bar.style.width = (done / entries.length * 100) + '%';
                    setStatus(`Bezig… ${done.toLocaleString('nl-NL')} van ${entries.length.toLocaleString('nl-NL')}`);
                }
                setStatus(`✅ Klaar: ${imported.toLocaleString('nl-NL')} nieuwe bezoeken geïmporteerd` +
                    (skipped ? `, ${skipped.toLocaleString('nl-NL')} overgeslagen (al aanwezig of geen webpagina).` : '.') +
                    ' Herlaad de pagina om ze te zien.');
            } catch (e) {
                setStatus(`❌ Gestopt na ${imported.toLocaleString('nl-NL')} bezoeken: ${e.message}. Opnieuw proberen kan gewoon, dubbele worden overgeslagen.`, true);
            }
            button.disabled = false;
        });
    </script>
@endsection
