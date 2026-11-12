@props(['level'])

<span {{ $attributes->class([
    'inline-flex w-fit items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset',
    'bg-yellow-50 text-yellow-800 ring-yellow-200' => $level === \App\Enums\OverdueLevel::Light,
    'bg-orange-50 text-orange-800 ring-orange-200' => $level === \App\Enums\OverdueLevel::Medium,
    'bg-red-50 text-red-800 ring-red-200' => $level === \App\Enums\OverdueLevel::High,
]) }}>
    <span class="size-1.5 rounded-full bg-current" aria-hidden="true"></span>
    {{ $level->label() }}
</span>
