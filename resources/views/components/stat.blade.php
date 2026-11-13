@props(['label', 'value', 'sub' => null, 'percent' => null])

<div class="rounded-xl bg-white p-4 shadow-sm">
    <div class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $label }}</div>
    <div class="mt-1 text-2xl font-semibold">{{ $value }}</div>
    @if ($percent !== null)
        <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100">
            <div @class([
                    'h-full rounded-full',
                    'bg-emerald-500' => $percent < 60,
                    'bg-amber-500' => $percent >= 60 && $percent < 85,
                    'bg-red-500' => $percent >= 85,
                 ]) style="width: {{ min(100, max(0, $percent)) }}%"></div>
        </div>
    @endif
    @if ($sub)
        <div class="mt-1 text-xs text-slate-500">{{ $sub }}</div>
    @endif
</div>
