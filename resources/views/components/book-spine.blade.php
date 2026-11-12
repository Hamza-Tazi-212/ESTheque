@props(["title"])

@php
    [$spineLabel, $spineTone] = match (true) {
        str_contains($title, 'Algorithm') => ['ALGO', 'forest'],
        str_contains($title, 'Python') => ['PY', 'sage'],
        str_contains($title, 'Data-Intensive') => ['DATA', 'sand'],
        str_contains($title, 'Networking') => ['NET', 'navy'],
        str_contains($title, 'Operating System') => ['OS', 'navy'],
        str_contains($title, 'Design Patterns') => ['DES', 'sand'],
        str_contains($title, 'Clean Code') => ['CODE', 'green'],
        default => [\Illuminate\Support\Str::upper(\Illuminate\Support\Str::limit($title, 4, '')), 'green'],
    };
@endphp
<div class="catalogue-book-spine" data-tone="{{ $spineTone }}" aria-hidden="true">
    <span>{{ $spineLabel }}</span>
    <span class="catalogue-spine-mark">EST ·<br>FÈS</span>
</div>
