@props(['tone' => 'neutral'])

<span {{ $attributes->class([
    'inline-flex w-fit items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold',
    'bg-emerald-50 text-emerald-800 ring-1 ring-inset ring-emerald-200' => $tone === 'success',
    'bg-slate-100 text-slate-600 ring-1 ring-inset ring-slate-200' => $tone === 'neutral',
    'bg-red-50 text-red-800 ring-1 ring-inset ring-red-200' => $tone === 'error',
    'bg-amber-50 text-amber-800 ring-1 ring-inset ring-amber-200' => $tone === 'warning',
]) }}>
    <span class="size-1.5 rounded-full bg-current" aria-hidden="true"></span>
    {{ $slot }}
</span>
