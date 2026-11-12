@extends('layouts.app')

@section('title', 'Catalogue des ouvrages')

@section('content')
    <section aria-labelledby="catalogue-title">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-800">Espace étudiant · EST Fès</p>
        <h1 id="catalogue-title" class="mt-3 text-3xl font-semibold tracking-tight sm:text-5xl">Le catalogue des ouvrages</h1>
        <p class="mt-5 max-w-2xl leading-relaxed text-slate-600">Recherchez un titre ou un auteur et consultez sa disponibilité. Pour emprunter un exemplaire, présentez-vous à la bibliothèque.</p>

        <form method="GET" action="{{ route('student.index') }}" role="search" class="mt-8 sm:mt-10">
            <label for="catalogue-search" class="mb-2 block text-sm font-medium text-slate-700">Titre ou auteur</label>
            <div class="flex flex-col gap-3 sm:flex-row">
                <div class="relative min-w-0 flex-1">
                    <svg class="pointer-events-none absolute top-3.5 left-4 size-5 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4.5 4.5"/></svg>
                    <input id="catalogue-search" name="q" type="search" value="{{ $search }}" maxlength="255" placeholder="Rechercher dans le catalogue…" @error('q') aria-invalid="true" aria-describedby="search-error" @enderror class="w-full rounded-xl border border-slate-300 bg-white py-3 pr-4 pl-12 text-base outline-none placeholder:text-slate-400 focus:border-emerald-700 focus:ring-2 focus:ring-emerald-700/15">
                </div>
                <button type="submit" class="rounded-xl bg-emerald-900 px-6 py-3 text-sm font-semibold text-white transition-colors hover:bg-emerald-800 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-emerald-800">Rechercher</button>
            </div>
            @error('q')
                <p id="search-error" class="mt-2 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </form>

        <div class="mt-10 mb-4 flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 pt-6">
            <h2 class="text-sm font-medium text-slate-600">{{ $books->count() }} {{ $books->count() === 1 ? 'ouvrage' : 'ouvrages' }}{{ $search !== '' ? ' pour votre recherche' : ' dans le catalogue' }}</h2>
            @if($search !== '')
                <a href="{{ route('student.index') }}" class="text-sm font-medium text-emerald-800 underline underline-offset-4 hover:text-emerald-950">Effacer la recherche</a>
            @endif
        </div>

        <div class="space-y-3">
            @forelse($books as $book)
                <details class="group rounded-xl border border-slate-200 bg-white open:border-emerald-200">
                    <summary class="book-summary grid cursor-pointer list-none grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4 gap-y-3 rounded-xl px-5 py-4 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-emerald-800 sm:px-6 md:grid-cols-[minmax(0,1fr)_auto_auto]">
                        <span class="min-w-0">
                            <span class="line-clamp-2 font-semibold leading-snug text-slate-900">{{ $book->title }}</span>
                            <span class="mt-1 block truncate text-sm text-slate-500">{{ $book->author }}</span>
                        </span>
                        <x-book-availability :available="$book->available_copies_count > 0" class="col-start-1 row-start-2 md:col-start-2 md:row-start-1" />
                        <svg class="col-start-2 row-span-2 row-start-1 size-5 text-slate-400 transition-transform group-open:rotate-180 motion-reduce:transition-none md:col-start-3 md:row-span-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                    </summary>
                    <div class="border-t border-slate-100 px-5 py-5 sm:px-6">
                        <p class="max-w-2xl break-words text-sm leading-relaxed text-slate-600">{{ \Illuminate\Support\Str::limit($book->summary, 180) }}</p>
                        <div class="mt-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <p class="text-sm text-slate-500">{{ $book->available_copies_count }} {{ $book->available_copies_count == 1 ? 'exemplaire disponible' : 'exemplaires disponibles' }} sur {{ $book->copies_count }}.</p>
                            <a href="{{ route('student.books.show', ['book' => $book, 'q' => $search !== '' ? $search : null]) }}" class="inline-flex w-fit items-center gap-2 rounded-lg bg-emerald-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-emerald-800">
                                Plus de détails <span aria-hidden="true">→</span>
                                <span class="sr-only">sur {{ $book->title }}</span>
                            </a>
                        </div>
                    </div>
                </details>
            @empty
                <div class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
                    <p class="font-semibold text-slate-800">{{ $search !== '' ? 'Aucun ouvrage trouvé' : 'Le catalogue est vide pour le moment' }}</p>
                    <p class="mt-2 text-sm text-slate-500">{{ $search !== '' ? 'Essayez un autre titre ou un autre nom d’auteur.' : 'Les ouvrages apparaîtront ici dès leur mise à disposition par la bibliothèque.' }}</p>
                </div>
            @endforelse
        </div>
    </section>
@endsection
