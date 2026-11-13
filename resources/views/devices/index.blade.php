@extends('layouts.app', ['title' => 'Apparaten'])

@section('content')
    <h1 class="mb-4 text-2xl font-semibold">⚙️ Apparaten</h1>

    @if ($new = session('new_token'))
        <div class="mb-6 rounded-xl border border-indigo-200 bg-indigo-50 p-4 text-sm">
            <p class="mb-2 font-semibold text-indigo-900">Token voor "{{ $new['device'] }}". Kopieer het nu, want het wordt maar één keer getoond:</p>
            <input readonly value="{{ $new['token'] }}" onclick="this.select()"
                   class="w-full rounded-lg border border-indigo-300 bg-white px-3 py-2 font-mono text-sm">
            <p class="mt-2 text-indigo-800">Server-URL voor de agent: <code class="font-mono">{{ url('/api/report') }}</code></p>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <section class="overflow-hidden rounded-xl bg-white shadow-sm lg:col-span-2">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-2">Naam</th>
                        <th class="px-4 py-2">Type</th>
                        <th class="px-4 py-2">Laatst gezien</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($devices as $item)
                        <tr>
                            <td class="px-4 py-2 font-medium">
                                <a href="{{ route('devices.dashboard', $item) }}" class="hover:underline">{{ $item->name }}</a>
                            </td>
                            <td class="px-4 py-2">{{ \App\Models\Device::TYPES[$item->type] ?? $item->type }}</td>
                            <td class="px-4 py-2 text-slate-500">{{ $item->last_seen_at?->diffForHumans() ?? 'nooit' }}</td>
                            <td class="px-4 py-2">
                                <div class="flex justify-end gap-2">
                                    <form method="POST" action="{{ route('devices.token', $item) }}"
                                          onsubmit="return confirm('Nieuw token maken? Het oude token werkt daarna niet meer.')">
                                        @csrf
                                        <button class="rounded bg-slate-100 px-2 py-1 text-xs hover:bg-slate-200">Nieuw token</button>
                                    </form>
                                    <form method="POST" action="{{ route('devices.destroy', $item) }}"
                                          onsubmit="return confirm('Apparaat en alle bijbehorende gegevens verwijderen?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="rounded bg-red-50 px-2 py-1 text-xs text-red-700 hover:bg-red-100">Verwijderen</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">Nog geen apparaten. Voeg rechts je laptop en telefoon toe.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        <form method="POST" action="{{ route('devices.store') }}" class="space-y-4 rounded-xl bg-white p-5 shadow-sm">
            @csrf
            <h2 class="font-semibold">Apparaat toevoegen</h2>
            <div>
                <label class="mb-1 block text-sm font-medium">Naam</label>
                <input name="name" value="{{ old('name') }}" placeholder="bv. Mijn laptop" required
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Type</label>
                <select name="type" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @foreach (\App\Models\Device::TYPES as $value => $label)
                        <option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button class="w-full rounded-lg bg-indigo-600 py-2 text-sm font-medium text-white hover:bg-indigo-700">Toevoegen</button>
        </form>
    </div>
@endsection
