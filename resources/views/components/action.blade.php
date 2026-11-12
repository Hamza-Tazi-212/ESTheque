@props(['href' => null, 'variant' => 'primary'])

@php
    $classes = match ($variant) {
        'secondary' => 'rounded-xl border border-brand-border bg-white px-6 py-3 text-sm font-semibold text-brand-green transition-colors hover:bg-brand-mint focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-emerald-800',
        'danger' => 'rounded-xl border border-red-300 bg-white px-6 py-3 text-sm font-semibold text-red-800 transition-colors hover:bg-red-50 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-red-800',
        default => 'rounded-xl bg-emerald-900 px-6 py-3 text-sm font-semibold text-white transition-colors hover:bg-emerald-800 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-emerald-800',
    };
    $attributes = $attributes->class([$classes, 'inline-flex items-center justify-center gap-2', 'disabled:cursor-not-allowed disabled:opacity-50' => $href === null]);
@endphp

@if($href !== null)
    <a href="{{ $href }}" {{ $attributes }}>{{ $slot }}</a>
@else
    <button {{ $attributes->merge(['type' => 'button']) }}>{{ $slot }}</button>
@endif
