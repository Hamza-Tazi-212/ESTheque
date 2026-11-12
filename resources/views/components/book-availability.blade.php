@props(['available'])

<span {{ $attributes->class([
    'inline-flex w-fit items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold',
    'bg-emerald-50 text-emerald-800 ring-1 ring-inset ring-emerald-200' => $available,
    'bg-slate-100 text-slate-600 ring-1 ring-inset ring-slate-200' => ! $available,
]) }}>
    <span class="size-1.5 rounded-full bg-current" aria-hidden="true"></span>
    {{ $available ? 'Disponible' : 'Indisponible' }}
</span>
