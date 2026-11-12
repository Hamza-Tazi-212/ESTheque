@extends('layouts.app')

@section('title', $book->title)

@section('content')
    <a href="{{ route('student.index', ['q' => $search !== '' ? $search : null]) }}" class="inline-flex items-center gap-2 text-sm font-medium text-emerald-800 underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-emerald-800"><span aria-hidden="true">←</span> Retour au catalogue</a>

    <article class="mt-8 grid items-start gap-8 sm:mt-10 sm:gap-12 md:grid-cols-[240px_minmax(0,1fr)]" aria-labelledby="book-title">
        <div class="mx-auto w-full max-w-60 md:mx-0" data-book-cover>
            @if($book->cover_image_url)
                <img src="{{ $book->cover_image_url }}" alt="Couverture de {{ $book->title }}" width="480" height="640" referrerpolicy="no-referrer" class="aspect-[3/4] w-full rounded-lg bg-slate-100 object-contain shadow-lg shadow-slate-900/10" data-cover-image>
            @endif
            <div class="{{ $book->cover_image_url ? 'hidden' : '' }} flex aspect-[3/4] items-center justify-center rounded-lg border border-slate-200 bg-slate-100 p-6 text-center text-sm text-slate-500" data-cover-fallback>Couverture indisponible</div>
        </div>

        <div class="min-w-0">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-800">Fiche de l’ouvrage</p>
            <h1 id="book-title" class="mt-3 break-words text-3xl font-semibold leading-tight tracking-tight sm:text-4xl">{{ $book->title }}</h1>
            <p class="mt-3 break-words text-lg text-slate-500">{{ $book->author }}</p>
            <div class="mt-6 flex flex-wrap items-center gap-3">
                <x-book-availability :available="$book->available_copies_count > 0" />
                <span class="text-sm text-slate-500">{{ $book->available_copies_count }} {{ $book->available_copies_count == 1 ? 'exemplaire disponible' : 'exemplaires disponibles' }} sur {{ $book->copies_count }}</span>
            </div>
            <p class="mt-5 border-l-2 border-emerald-800 pl-4 text-sm leading-relaxed text-slate-600">Pour emprunter cet ouvrage, présentez-vous à la bibliothèque et récupérez un exemplaire auprès du gérant.</p>

            <section class="mt-9 border-t border-slate-200 pt-7" aria-labelledby="summary-title">
                <h2 id="summary-title" class="text-lg font-semibold">Résumé</h2>
                <p class="mt-3 whitespace-pre-line break-words leading-7 text-slate-600">{{ $book->summary }}</p>
            </section>
            <section class="mt-8 border-t border-slate-200 pt-7" aria-labelledby="contents-title">
                <h2 id="contents-title" class="text-lg font-semibold">Sommaire</h2>
                <p class="mt-3 whitespace-pre-line break-words leading-7 text-slate-600">{{ $book->table_of_contents }}</p>
            </section>
        </div>
    </article>
@endsection
