@extends('layouts.app')

@section('title', $book->title)

@section('content')
    @php
        $translations = config('book-display-fr', []);
        $french = $translations[$book->open_library_edition] ?? $translations[$book->isbn] ?? [];
        foreach (config('catalogue-disciplines', []) as $discipline) {
            if (isset($discipline['books'][$book->isbn])) {
                $french['summary'] = $discipline['books'][$book->isbn];
                $french['subjects'] = $discipline['label'];
                break;
            }
        }
        $languageNames = ['eng' => 'anglais', 'fre' => 'français', 'fra' => 'français', 'ara' => 'arabe',
            'spa' => 'espagnol', 'ger' => 'allemand', 'deu' => 'allemand', 'ita' => 'italien'];
        $languages = $book->language ? array_map(
            fn ($code) => $languageNames[trim($code)] ?? trim($code),
            explode(',', $book->language)
        ) : [];
        $summary = $french['summary'] ?? $book->summary;
    @endphp
    <a href="{{ route('student.index', ['q' => $search !== '' ? $search : null]) }}" class="inline-flex items-center gap-2 text-sm font-medium text-emerald-800 underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-emerald-800"><span aria-hidden="true">←</span> Retour au catalogue</a>

    <article class="mt-8 grid items-start gap-8 sm:mt-10 sm:gap-12 md:grid-cols-[240px_minmax(0,1fr)]" aria-labelledby="book-title">
        <div class="mx-auto w-full max-w-60 md:mx-0" data-book-cover>
            @if($book->coverUrl())
                <img src="{{ $book->coverUrl() }}" alt="Couverture de {{ $book->title }}" width="480" height="640" referrerpolicy="no-referrer" class="aspect-[3/4] w-full rounded-lg bg-slate-100 object-contain shadow-lg shadow-slate-900/10" data-cover-image @if($book->open_library_cover) data-open-library-cover @endif onerror="this.hidden = true; this.nextElementSibling.classList.remove('hidden')">
            @endif
            <div class="{{ $book->coverUrl() ? 'hidden' : '' }} flex aspect-[3/4] items-center justify-center rounded-lg border border-slate-200 bg-slate-100 p-6 text-center text-sm text-slate-500" data-cover-fallback>Couverture indisponible</div>
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

            @if($book->isbn || $book->publisher || $book->subtitle || $book->subjects || isset($french['subjects']) || $book->edition_statement || $book->publication_date || $book->language)
                <dl class="mt-5 space-y-1 break-words text-sm text-slate-600">
                    @foreach(['subtitle' => 'Sous-titre', 'isbn' => 'ISBN', 'publisher' => 'Éditeur', 'publication_date' => 'Publication', 'edition_statement' => 'Édition', 'language' => 'Langue', 'subjects' => 'Sujets'] as $field => $label)
                        @php($value = $field === 'language' ? implode(', ', array_unique($languages)) : ($french[$field] ?? $book->$field))
                        @if($value)<div><dt class="inline font-semibold">{{ $label }}</dt><dd class="inline"> · {{ $value }}</dd></div>@endif
                    @endforeach
                </dl>
            @endif

            <section class="mt-9 border-t border-slate-200 pt-7" aria-labelledby="summary-title">
                <h2 id="summary-title" class="text-lg font-semibold">Résumé</h2>
                <p class="mt-3 whitespace-pre-line break-words leading-7 text-slate-600">{{ filled($summary) ? $summary : 'Résumé indisponible pour cette édition.' }}</p>
            </section>
            @if(filled($book->table_of_contents))
            <section class="mt-8 border-t border-slate-200 pt-7" aria-labelledby="contents-title">
                <h2 id="contents-title" class="text-lg font-semibold">Sommaire</h2>
                <p class="mt-3 whitespace-pre-line break-words leading-7 text-slate-600">{{ $book->table_of_contents }}</p>
            </section>
            @endif
        </div>
    </article>
@endsection
