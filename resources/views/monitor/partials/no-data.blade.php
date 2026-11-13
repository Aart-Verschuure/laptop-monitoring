@if (! $device->last_seen_at)
    <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
        <strong>Nog geen gegevens ontvangen van dit apparaat.</strong>
        Installeer de agent (zie <code>agents/README.md</code> in het project) met het token van dit apparaat.
        Een nieuw token maak je aan op de pagina <a href="{{ route('devices.index') }}" class="underline">Apparaten</a>.
    </div>
@endif
