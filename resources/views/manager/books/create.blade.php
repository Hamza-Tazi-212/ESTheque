@extends('layouts.app')

@section('title', 'Ajouter un ouvrage')

@section('content')
<section class="mx-auto max-w-4xl space-y-6" aria-labelledby="book-create-title">
    <h1 id="book-create-title" class="text-3xl font-semibold text-brand-green">Ajouter un ouvrage</h1>

    <form action="{{ route('manager.books.create') }}" method="get" class="rounded-2xl border border-library-border bg-white p-5 sm:p-6" role="search">
        <div class="grid gap-4 sm:grid-cols-[auto_minmax(0,1fr)_auto] sm:items-end">
            <label class="grid gap-2 text-sm font-semibold">
                Rechercher par
                <select name="mode" class="rounded-xl border border-slate-300 bg-white px-3 py-3 focus-visible:outline-2 focus-visible:outline-brand-green">
                    @foreach(['isbn' => 'ISBN', 'title' => 'Titre', 'author' => 'Auteur'] as $value => $label)
                        <option value="{{ $value }}" @selected(($mode ?? 'isbn') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="grid gap-2 text-sm font-semibold">
                Recherche Open Library
                <input name="q" value="{{ old('q', $query) }}" required maxlength="150" class="min-w-0 rounded-xl border border-slate-300 px-4 py-3 focus-visible:outline-2 focus-visible:outline-brand-green">
            </label>
            <x-action type="submit">Rechercher</x-action>
        </div>
    </form>

    @if($errors->any())
        <ul class="rounded-xl border border-red-200 bg-red-50 p-4 text-red-800" role="alert">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    @endif

    @if($notice)
        <x-info-banner>{{ $notice }}</x-info-banner>
    @endif

    @if($existing)
        <x-info-banner>
            Cette édition est déjà enregistrée dans ESThèque.
            <x-slot:action><x-action :href="route('manager.books.show', $existing)">Consulter la fiche et les exemplaires</x-action></x-slot:action>
        </x-info-banner>
    @elseif(!$token && ($results || $work || in_array($mode, ['title', 'author'], true)))
        <section class="space-y-3" aria-labelledby="search-results-title">
            <h2 id="search-results-title" class="text-xl font-semibold text-brand-green">{{ $work ? 'Choisir une édition' : 'Choisir un ouvrage' }}</h2>
            @forelse($results as $result)
                <a class="block rounded-xl border border-library-border bg-white p-4 hover:border-brand-border focus-visible:outline-2 focus-visible:outline-brand-green"
                   href="{{ route('manager.books.create', $work ? ['edition' => $result['key'], 'work' => $work] : ['work' => $result['key']]) }}">
                    <span class="block font-semibold text-brand-green">{{ $result['title'] }}</span>
                    @if($result['detail'])<span class="text-sm text-slate-600">{{ $result['detail'] }}</span>@endif
                </a>
            @empty
                @unless($notice)<p class="text-slate-600">Aucun résultat. Vous pouvez remplir la fiche manuellement.</p>@endunless
            @endforelse
            @if($work && ($more || $page > 1))
                <nav aria-label="Pages des éditions" class="flex flex-wrap gap-3">
                    @if($page > 1)<x-action variant="secondary" :href="route('manager.books.create', ['work' => $work, 'page' => $page - 1])">Précédent</x-action>@endif
                    @if($more && $page < 100)<x-action variant="secondary" :href="route('manager.books.create', ['work' => $work, 'page' => $page + 1])">Suivant</x-action>@endif
                </nav>
            @endif
        </section>
    @endif

    @unless($existing)
        <form action="{{ route('manager.books.store') }}" method="post" class="space-y-5 rounded-2xl border border-library-border bg-white p-5 sm:p-6">
            @csrf
            <h2 class="text-xl font-semibold text-brand-green">{{ $token ? 'Vérifier la fiche avant enregistrement' : 'Saisie manuelle' }}</h2>
            @if($token)
                <input type="hidden" name="preview_token" value="{{ $token }}">
                <div class="flex flex-wrap items-center gap-4">
                    @php($previewBook = new \App\Models\Book(\Illuminate\Support\Arr::except($metadata, ['summary_from_work', 'subjects_from_work'])))
                    <x-book-thumbnail :book="$previewBook" />
                    <a class="underline text-brand-green" href="{{ $previewBook->open_library_source_url }}">Édition sur Open Library</a>
                    @if($metadata['open_library_work'])
                        <a class="underline text-brand-green" href="{{ route('manager.books.create', ['work' => $metadata['open_library_work']]) }}">Choisir une autre édition</a>
                    @endif
                    <a class="underline text-brand-green" href="{{ route('manager.books.create') }}">Annuler l’import</a>
                </div>

            @endif
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach(['title' => 'Titre', 'subtitle' => 'Sous-titre', 'author' => 'Auteur(s)', 'isbn' => 'ISBN', 'publisher' => 'Éditeur', 'publication_date' => 'Date de publication', 'edition_statement' => 'Mention d’édition', 'language' => 'Langue'] as $field => $label)
                    <label class="grid gap-2 text-sm font-semibold">
                        {{ $label }}
                        <input name="{{ $field }}" value="{{ old($field, $metadata[$field] ?? '') }}" maxlength="{{ $field === 'isbn' ? 32 : 255 }}" @required($field === 'title')
                            class="min-w-0 rounded-xl border border-slate-300 px-4 py-3 focus-visible:outline-2 focus-visible:outline-brand-green">
                    </label>
                @endforeach
            </div>
            @foreach(['summary' => 'Résumé', 'subjects' => 'Sujets', 'table_of_contents' => 'Sommaire'] as $field => $label)
                <label class="grid gap-2 text-sm font-semibold">
                    {{ $label }} @if($metadata[$field.'_from_work'] ?? false) <span class="font-normal text-slate-600">D’après la notice de l’œuvre</span> @endif
                    <textarea name="{{ $field }}" rows="3" maxlength="12000" class="rounded-xl border border-slate-300 px-4 py-3 focus-visible:outline-2 focus-visible:outline-brand-green">{{ old($field, $metadata[$field] ?? '') }}</textarea>
                </label>
            @endforeach
            <x-info-banner>La fiche est enregistrée sans exemplaire. Sa disponibilité dépend du stock physique.</x-info-banner>
            <div class="flex flex-wrap gap-3">
                <x-action type="submit">Enregistrer l’ouvrage</x-action>
                <x-action variant="secondary" :href="route('manager.index')">Retour au tableau de bord</x-action>
            </div>
        </form>
    @endunless
</section>
@endsection
