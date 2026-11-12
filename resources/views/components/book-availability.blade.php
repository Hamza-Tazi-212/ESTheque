@props(['available'])

<x-status-badge :tone="$available ? 'success' : 'neutral'" {{ $attributes }}>
    {{ $available ? 'Disponible' : 'Indisponible' }}
</x-status-badge>
