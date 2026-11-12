@props(['action' => null])

<aside {{ $attributes->class(['flex-wrap' => $action?->isNotEmpty()]) }} style="display:flex; align-items:flex-start; gap:.75rem; padding:1rem 1.25rem; border-inline-start:3px solid var(--color-brand-green); background:#e8f4eb; color:#425d50; line-height:1.6;">
    <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--color-brand-green)" stroke-width="1.8" stroke-linecap="round" style="flex:none; margin-top:.15rem;"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 7h.01"/></svg>
    <p @if($action?->isNotEmpty()) class="flex-[1_1_15rem]" @endif>{{ $slot }}</p>
    {{ $action }}
</aside>
