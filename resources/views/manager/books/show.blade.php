@extends('layouts.app')

@section('title', $book->title)

@section('content')
<section class="mx-auto max-w-3xl space-y-5" aria-labelledby="registered-book-title">
    @if(session('duplicate'))<x-info-banner>Cette édition est déjà enregistrée.</x-info-banner>@endif
    @if(session('registered'))<x-info-banner>L’ouvrage a été enregistré.</x-info-banner>@endif
    <article class="rounded-2xl border border-library-border bg-white p-6">
        <div class="flex flex-wrap items-center gap-4">
            <x-book-thumbnail :book="$book" />
            <div class="min-w-0">
                <h1 id="registered-book-title" class="break-words text-2xl font-semibold text-brand-green">{{ $book->title }}</h1>
                <p class="text-slate-600">{{ $book->author }}</p>
                @if($book->isbn)<p class="text-sm text-slate-600">ISBN {{ $book->isbn }}</p>@endif
            </div>
        </div>
        @if($book->summary)<p class="mt-5 whitespace-pre-line break-words text-slate-600">{{ $book->summary }}</p>@endif
        @if($book->open_library_source_url)
            <a class="mt-4 inline-block text-brand-green underline" href="{{ $book->open_library_source_url }}">Édition sur Open Library</a>
        @endif
        <h2 class="mt-6 text-lg font-semibold text-brand-green">Exemplaires physiques</h2>
        <ul class="mt-2 space-y-2">
            @forelse($book->copies as $copy)
                <li class="flex flex-wrap items-center gap-3"><span class="break-all">{{ $copy->qr_code }}</span><x-book-availability :available="$copy->status === \App\Enums\CopyStatus::Available" /></li>
            @empty
                <li class="text-slate-600">Aucun exemplaire enregistré.</li>
            @endforelse
        </ul>
    </article>
    <x-action :href="route('manager.index')">Retour au tableau de bord</x-action>
</section>
@endsection
