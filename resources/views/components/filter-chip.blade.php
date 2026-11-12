@props(['href', 'selected'])

<a href="{{ $href }}"
    @if($selected) aria-current="true" @endif
    class="focus-visible:outline-2 focus-visible:outline-offset-4"
    style="border:1px solid var(--color-brand-border); border-radius:1rem; padding:.55rem .9rem; color:var(--color-brand-green); font-size:.875rem; font-weight:600; background:{{ $selected ? 'var(--color-brand-mint)' : '#fff' }};">
    @if($selected)<span aria-hidden="true">✓</span>@endif
    {{ $slot }}
</a>
