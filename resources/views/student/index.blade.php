@extends('layouts.app')

@section('title', 'Catalogue des ouvrages')

@section('hero')
    <style>
        .student-hero { background: #e8f4eb; color: #104f3d; }
        .student-hero-grid { max-width: 85rem; margin-inline: auto; padding: clamp(2rem, 6vw, 6rem) clamp(1.25rem, 4vw, 4rem); display: grid; align-items: center; gap: clamp(2rem, 6vw, 6rem); }
        .student-hero-books { display: block; width: 100%; height: auto; }
        .student-hero-title { font: 500 clamp(2.5rem, 4.5vw, 4.4rem)/1.04 Georgia, serif; letter-spacing: -.045em; }
        .student-hero-title em { display: block; color: #34775f; }
        .student-hero-copy { margin-top: 1.5rem; color: #425d50; line-height: 1.7; }
        .student-hero-copy p + p { margin-top: .5rem; }
        .student-hero-actions { display: flex; justify-content: center; margin-top: 1.75rem; }
        .student-hero-link { display: inline-flex; gap: .75rem; align-items: center; padding: .9rem 1.25rem; border-radius: 1rem; background: #104f3d; color: white; font-weight: 600; }
        .student-hero-link:hover { background: #0b3b2d; }
        .student-hero-link:focus-visible { outline: 2px solid #104f3d; outline-offset: 4px; }
        .catalogue-book-card { display:grid; grid-template-columns:auto minmax(0,1fr) auto auto; align-items:center; gap:1rem; padding:1rem; background:white; border:1px solid #cdded5; border-radius:.9rem; }
        .catalogue-book-card:hover { border-color:#79a891; }
        .catalogue-book-card:focus { outline:none; }
        .catalogue-book-card:focus-visible { background:#eef7f0; }
        .catalogue-book-eye { opacity:0; }
        .catalogue-book-card:hover .catalogue-book-eye,
        .catalogue-book-card:focus-visible .catalogue-book-eye { opacity:1; }
        .catalogue-book-info h3 { font-weight:700; color:#104f3d; overflow-wrap:anywhere; }
        .catalogue-book-info p { color:#475569; font-size:.875rem; margin-top:.25rem; }
        .catalogue-book-open { display:grid; place-items:center; width:2.5rem; height:2.5rem; border:1px solid #cdded5; border-radius:50%; color:#104f3d; background:#f3f9f5; font-size:1.25rem; cursor:pointer; }
        .catalogue-page-arrow { grid-area:auto; }
        .catalogue-book-open:hover, .catalogue-book-open:focus-visible { background:#d1fae5; }
        #catalogue-book-dialog { position:fixed; inset:0; margin:auto; width:min(48rem,calc(100% - 2rem)); max-height:calc(100% - 2rem); overflow:auto; overscroll-behavior:contain; scrollbar-width:none; padding:clamp(1.25rem,4vw,2rem); border-radius:1rem; background:white; color:#104f3d; }
html:has(#catalogue-book-dialog[open]) { overflow:hidden; } #catalogue-book-dialog::-webkit-scrollbar { display:none; }
#catalogue-book-dialog:focus { outline:none; }
        #catalogue-book-dialog::backdrop { background:#102c22a6; backdrop-filter:blur(3px); }
        .catalogue-dialog-top { display:flex; justify-content:space-between; align-items:center; gap:1rem; font-size:.85rem; font-weight:600; }
        .catalogue-dialog-identity { display:flex; align-items:center; gap:1rem; margin-top:1.25rem; }
        .catalogue-dialog-identity > div { min-width:0; }
        .catalogue-dialog-identity .catalogue-book-spine { flex:none; }
        .catalogue-dialog-book-text { flex:1; display:flex; flex-wrap:wrap; align-items:baseline; justify-content:space-between; gap:.35rem .75rem; }
        .catalogue-dialog-book-text #book-title { width:100%; }
        .catalogue-dialog-contents { margin-top:1.5rem; padding-top:1rem; border-top:1px solid #cdded5; }
        .catalogue-dialog-contents h4 { font-weight:600; }
        .catalogue-dialog-contents ul { margin:.75rem 0 0; padding-inline-start:1.25rem; list-style:disc; color:#475569; }
        .catalogue-dialog-details #book-title { margin-top:0; font:500 clamp(1.6rem,4vw,2.4rem)/1.12 Georgia,serif; overflow-wrap:anywhere; }
        @media (max-width: 540px) {
            .catalogue-book-card { grid-template-columns:auto minmax(0,1fr) auto; gap:.6rem .8rem; }
            :is(.catalogue-book-spine, .catalogue-book-thumbnail) { grid-row:span 2; }
            .catalogue-book-info { grid-column:2; }
            .catalogue-book-open { grid-column:3; grid-row:1 / span 2; }
        }
        .catalogue-mobile-filters { display: none; }
        @media (max-width: 39.99rem) {
            .catalogue-desktop-filters { display: none; }
            .catalogue-mobile-filters {
                display: flex; flex-wrap: wrap; align-items: center; gap: .65rem; margin-top: 1.5rem;
            }
            .catalogue-mobile-filters label, .catalogue-mobile-filters select {
                min-height: 44px; border: 1px solid #a8c8b9; border-radius: 1rem;
                padding: .55rem .75rem; background: #fff; color: #104f3d; font-size: 1rem;
            }
            .catalogue-mobile-filters label { display: inline-flex; align-items: center; gap: .45rem; }
            .catalogue-mobile-filters label:has(input:checked) { background: #d1fae5; }
            .catalogue-mobile-filters input[type="checkbox"] { accent-color: #104f3d; }
            .catalogue-mobile-filters select { flex: 1 1 10rem; min-width: 0; max-width: 100%; }
        }
        .catalogue-desktop-filters { flex-wrap: wrap; gap: .65rem; margin-top: 1.5rem; }
        @media (min-width: 40rem) { .catalogue-desktop-filters { display: flex; } }
        .catalogue-suggestion { display:block; width:100%; padding:.55rem .75rem; border-radius:.45rem; text-align:start; color:#104f3d; cursor:pointer; }
        .catalogue-suggestion:hover, .catalogue-suggestion:focus-visible { background:#d1fae5; }
        #catalogue { scroll-margin-top: 12rem; outline: none; }
        @media (min-width: 800px) { .student-hero-grid { grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr); } }
    </style>
    <section class="student-hero" aria-labelledby="student-hero-title">
        <div class="student-hero-grid">
            <img class="student-hero-books" src="{{ asset('estheque-hero-books.png') }}" alt="" width="1774" height="887">
            <div>
                <h1 class="student-hero-title" id="student-hero-title">Tous les savoirs, <em>un seul espace.</em></h1>
                <div class="student-hero-copy">
                    <p>Un ouvrage à découvrir, une idée à approfondir, un projet à construire.</p>
                    <p>Retrouvez les ressources de votre école dans un catalogue pensé pour vous.</p>
                </div>
                <div class="student-hero-actions">
                    <a class="student-hero-link" href="#catalogue">Découvrir le catalogue <span aria-hidden="true" style="display:grid; place-items:center; flex:none; width:2rem; height:2rem; border-radius:50%; border:1px solid #a7f3d0; background:#d1fae5; color:#104f3d;"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4v15m-6-6 6 6 6-6"/></svg></span></a>
                </div>
            </div>
        </div>
        <svg aria-hidden="true" viewBox="0 0 1440 72" preserveAspectRatio="none" style="display:block;width:100%;height:clamp(1.5rem,5vw,4.5rem);">
            <path d="M0 45C280 98 520 63 755 28S1160-10 1440 25V72H0Z" fill="#f8fafc"/>
            <path d="M0 45C280 98 520 63 755 28S1160-10 1440 25" fill="none" stroke="#bfd9c7" stroke-width="1.6" opacity=".8" vector-effect="non-scaling-stroke"/>
        </svg>
    </section>
@endsection


@section('content')
    <section id="catalogue" aria-labelledby="catalogue-title" tabindex="-1">
        <h2 id="catalogue-title" style="font-family: Georgia, serif; color: #104f3d;" class="mt-3 text-3xl font-semibold tracking-tight sm:text-5xl">Explorer les ouvrages</h2>
        <p class="mt-5 max-w-2xl leading-relaxed text-slate-600">Le bon livre, au bon moment. Recherchez par titre, auteur ou domaine.</p>

        <form method="GET" action="{{ route('student.index') }}#catalogue" role="search" class="mt-8 sm:mt-10">
            <label for="catalogue-search" class="sr-only">Titre, auteur ou domaine</label>
            <div class="flex flex-col gap-3 sm:flex-row">
                <div class="relative min-w-0 flex-1">
                    <svg class="pointer-events-none absolute top-3.5 left-4 size-5 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4.5 4.5"/></svg>
                    <input id="catalogue-search" name="q" type="search" autocomplete="off" value="{{ $search }}" maxlength="255" placeholder="Titre, auteur ou domaine…" @error('q') aria-invalid="true" aria-describedby="search-error" @enderror class="w-full rounded-xl border border-slate-300 bg-white py-3 pr-4 pl-12 text-base outline-none placeholder:text-slate-400 focus:border-emerald-700 focus:ring-2 focus:ring-emerald-700/15">
                    <div id="catalogue-suggestions" hidden style="position:absolute; inset-inline:0; top:100%; z-index:20; margin-top:.4rem; padding:.35rem; background:white; border:1px solid #a8c8b9; border-radius:.75rem; box-shadow:0 8px 24px #104f3d20;"></div>
                </div>
                <button type="submit" class="rounded-xl bg-emerald-900 px-6 py-3 text-sm font-semibold text-white transition-colors hover:bg-emerald-800 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-emerald-800">Rechercher</button>
            </div>
            @error('q')
                <p id="search-error" class="mt-2 text-sm text-red-700">{{ $message }}</p>
            @enderror
            @if($filter !== 'all')
                <input type="hidden" name="filter" value="{{ $filter }}">
            @endif
            @if($availableOnly && $filter !== 'available')
                <input type="hidden" name="available" value="1">
            @endif
        </form>

        <form method="GET" action="{{ route('student.index') }}#catalogue" class="catalogue-mobile-filters" aria-label="Filtres du catalogue" data-mobile-filters>
            @if($search !== '')
                <input type="hidden" name="q" value="{{ $search }}">
            @endif
            <label>
                <input type="checkbox" name="available" value="1" @checked($availableOnly)>
                Disponibles
            </label>
            <select name="filter" aria-label="Domaine">
                <option value="all" @selected(in_array($filter, ['all', 'available'], true))>Tous les domaines</option>
                @foreach($filters as $key => $label)
                    @continue(in_array($key, ['all', 'available'], true))
                    <option value="{{ $key }}" @selected($filter === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <noscript><x-action type="submit">Appliquer</x-action></noscript>
        </form>

        <nav aria-label="Filtres du catalogue" class="catalogue-desktop-filters">
            @foreach($filters as $key => $label)
                <x-filter-chip :href="route('student.index', ['q' => $search ?: null, 'filter' => $key === 'all' ? null : $key]).'#catalogue'" :selected="$filter === $key">{{ $label }}</x-filter-chip>
            @endforeach
        </nav>

        <x-info-banner class="mt-8"><strong style="color:#104f3d;">Comment emprunter ?</strong> Consultez la disponibilité d’un ouvrage, puis présentez-vous à la bibliothèque de l’EST Fès pour demander votre exemplaire.</x-info-banner>

        <div class="mt-6 mb-4 flex flex-wrap items-center justify-between gap-3">
            <h2 id="catalogue-results-heading" tabindex="-1" class="text-sm font-medium text-slate-600">{{ $books->total() }} {{ $books->total() === 1 ? 'ouvrage' : 'ouvrages' }}{{ $search !== '' ? ' pour votre recherche' : ' dans le catalogue' }}</h2>
            @if($search !== '')
                <a href="{{ route('student.index') }}" class="text-sm font-medium text-emerald-800 underline underline-offset-4 hover:text-emerald-950">Effacer la recherche</a>
            @endif
        </div>

        <div class="space-y-3">
            @forelse($books as $book)
                <a class="catalogue-book-card" data-book-details href="{{ route('student.books.show', ['book' => $book, 'q' => $search !== '' ? $search : null]) }}" aria-label="Voir la fiche de {{ $book->title }}" aria-haspopup="dialog">
                    <x-book-thumbnail :book="$book" />
                    <div class="catalogue-book-info">
                        <h3 data-book-title>{{ $book->title }}</h3>
                        <p>{{ $book->author }}</p>
                    </div>
                    <x-book-availability :available="$book->available_copies_count > 0" />
                    <span class="catalogue-book-open catalogue-book-eye" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-6 10-6 10 6 10 6-3.6 6-10 6-10-6-10-6Z"/><circle cx="12" cy="12" r="2.5"/></svg></span>
                </a>
            @empty
                <div class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
                    <p class="font-semibold text-slate-800">{{ ($search !== '' || $filter !== 'all' || $availableOnly) ? 'Aucun ouvrage trouvé' : 'Le catalogue est vide pour le moment' }}</p>
                    <p class="mt-2 text-sm text-slate-500">{{ ($search !== '' || $filter !== 'all' || $availableOnly) ? 'Essayez une autre recherche ou un autre filtre.' : 'Les ouvrages apparaîtront ici dès leur mise à disposition par la bibliothèque.' }}</p>
                </div>
            @endforelse
        </div>

        @if($books->hasPages())
            <nav data-catalogue-pagination aria-label="Pagination des ouvrages" class="mt-6 flex flex-wrap items-center justify-center gap-3">
                @if($books->previousPageUrl())
                    <a class="catalogue-book-open catalogue-page-arrow" href="{{ $books->previousPageUrl() }}" rel="prev" aria-label="Page précédente"><svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg></a>
                @endif
                <span class="text-sm text-slate-600">Page {{ $books->currentPage() }} sur {{ $books->lastPage() }}</span>
                @if($books->nextPageUrl())
                    <a class="catalogue-book-open catalogue-page-arrow" href="{{ $books->nextPageUrl() }}" rel="next" aria-label="Page suivante"><svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg></a>
                @endif
            </nav>
        @endif
    </section>
    <dialog id="catalogue-book-dialog" aria-labelledby="catalogue-dialog-title">
        <div class="catalogue-dialog-top">
            <h2 id="catalogue-dialog-title">Fiche de l’ouvrage</h2>
            <form method="dialog"><button class="catalogue-book-open" aria-label="Fermer la fiche" autofocus><svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m6 6 12 12M18 6 6 18"/></svg></button></form>
        </div>
        <div data-dialog-content aria-live="polite"></div>
    </dialog>
<script>
    const bookDialog = document.getElementById('catalogue-book-dialog');
    const bookContent = bookDialog.querySelector('[data-dialog-content]');
    let bookRequest;

    bookDialog.addEventListener('close', () => bookRequest?.abort());
    bookDialog.addEventListener('click', (event) => {
        if (event.target !== bookDialog) return;
        const bounds = bookDialog.getBoundingClientRect();
        if (event.clientX < bounds.left || event.clientX > bounds.right
            || event.clientY < bounds.top || event.clientY > bounds.bottom) bookDialog.close();
    });

    document.addEventListener('click', async (event) => {
        const link = event.target.closest('[data-book-details]');
        if (!link || event.defaultPrevented || event.button !== 0 || !bookDialog.showModal
            || event.ctrlKey || event.metaKey || event.altKey) return;
        event.preventDefault();
        bookRequest?.abort();
        const request = new AbortController();
        bookRequest = request;
        bookContent.textContent = 'Chargement de la fiche…';
        bookDialog.showModal();

        try {
            const response = await fetch(link.href, { signal: request.signal });
            if (!response.ok || response.redirected) throw new Error('Fiche indisponible');
            const page = new DOMParser().parseFromString(await response.text(), 'text/html');
            const details = page.querySelector('article[aria-labelledby="book-title"]');
            if (!details) throw new Error('Fiche introuvable');
            if (request.signal.aborted || !bookDialog.open) return;

            const coverUrl = details.querySelector('[data-open-library-cover]')?.getAttribute('src');
            details.querySelector('[data-book-cover]')?.remove();
            details.className = 'catalogue-dialog-details';
            const heading = details.querySelector('#book-title');
            heading.outerHTML = '<h3 id="book-title"></h3>';
            details.querySelector('#book-title').textContent = heading.textContent;
            details.querySelector('p')?.remove();
            details.querySelector('.border-l-2')?.remove();

            const title = details.querySelector('#book-title');
            const author = title.nextElementSibling;
            const identity = document.createElement('div');
            identity.className = 'catalogue-dialog-identity';
            title.before(identity);
            const spine = link.querySelector('.catalogue-book-thumbnail, .catalogue-book-spine')?.cloneNode(true);
            const cover = spine?.querySelector('img');
            if (cover && coverUrl) {
                cover.hidden = false;
                cover.loading = 'eager';
                cover.src = coverUrl;
            }
            if (spine) identity.append(spine);
            const text = document.createElement('div');
            text.append(title, author);
            identity.append(text);

            const availability = identity.nextElementSibling;
            if (availability) {
                const count = availability.lastElementChild;
                const compact = count.textContent.trim().match(/^(\d+) exemplaires? disponibles? sur (\d+)$/);
                if (compact) {
                    count.textContent = `${compact[1]} sur ${compact[2]} disponible${Number(compact[1]) > 1 ? 's' : ''}`;
                }
                count.className = availability.firstElementChild.className;
                text.className = 'catalogue-dialog-book-text';
                text.append(count);
                availability.remove();
            }

            const contents = details.querySelector('[aria-labelledby="contents-title"]');
            if (contents) {
                const lines = contents.querySelector('p').textContent.trim().split(/\r?\n/).map(line => line.trim()).filter(Boolean);
                if (lines.length) {
                    const disclosure = document.createElement('section');
                    disclosure.className = 'catalogue-dialog-contents';
                    const summary = document.createElement('h4');
                    summary.textContent = 'Sommaire';
                    disclosure.append(summary);
                    const list = document.createElement('ul');
                    for (const line of lines) {
                        const item = document.createElement('li');
                        item.textContent = line;
                        list.append(item);
                    }
                    disclosure.append(list);
                    contents.replaceWith(disclosure);
                } else {
                    contents.remove();
                }
            }
            bookContent.replaceChildren(details);
        } catch {
            if (request.signal.aborted) return;
            const fallback = document.createElement('a');
            fallback.href = link.href;
            fallback.textContent = 'Ouvrir la fiche de l’ouvrage';
            fallback.className = 'underline';
            bookContent.replaceChildren('La fiche n’a pas pu se charger. ', fallback);
        }
    });

    let suggestionTimer;
    let suggestionRequest;

    function clearSuggestions() {
        clearTimeout(suggestionTimer);
        suggestionRequest?.abort();
        const list = document.getElementById('catalogue-suggestions');
        list.hidden = true;
        list.replaceChildren();
    }

    document.addEventListener('input', (event) => {
        const input = event.target;
        if (input.id !== 'catalogue-search') return;
        clearSuggestions();
        const term = input.value.trim();
        if (term.length < 2) return;

        suggestionTimer = setTimeout(async () => {
            const request = new AbortController();
            suggestionRequest = request;
            const url = new URL(input.form.action);
            url.search = new URLSearchParams(new FormData(input.form));
            try {
                const response = await fetch(url, { signal: request.signal });
                if (!response.ok || response.redirected) return;
                const page = new DOMParser().parseFromString(await response.text(), 'text/html');
                if (request.signal.aborted || !input.isConnected || input.value.trim() !== term) return;
                const list = document.getElementById('catalogue-suggestions');
                const titles = [...new Set([...page.querySelectorAll('[data-book-title]')].map(item => item.textContent.trim()))];
                for (const title of titles.slice(0, 6)) {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'catalogue-suggestion';
                    button.textContent = title;
                    button.addEventListener('click', () => {
                        input.value = title;
                        clearSuggestions();
                        input.form.requestSubmit();
                    });
                    list.append(button);
                }
                list.hidden = !list.children.length;
            } catch {
                // The regular search remains available if suggestions fail.
            }
        }, 200);
    });

    document.addEventListener('keydown', (event) => {
        const list = document.getElementById('catalogue-suggestions');
        const input = document.getElementById('catalogue-search');
        if (list.hidden || (event.target !== input && !list.contains(event.target))) return;
        if (event.key === 'Escape') {
            clearSuggestions();
            input.focus({ preventScroll: true });
        } else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            const buttons = [...list.children];
            const index = buttons.indexOf(event.target);
            const next = event.key === 'ArrowDown' ? (index + 1) % buttons.length
                : index <= 0 ? buttons.length - 1 : index - 1;
            event.preventDefault();
            buttons[next].focus({ preventScroll: true });
        }
    });

    document.addEventListener('pointerdown', (event) => {
        if (!event.target.closest('#catalogue-search, #catalogue-suggestions')) clearSuggestions();
    });

    let filterRequest;

    async function updateCatalogue(url, saveHistory, focusSearch = false, pagination = false, mobileControl = null) {
        clearSuggestions();
        filterRequest?.abort();
        const request = new AbortController();
        filterRequest = request;
        try {
            const response = await fetch(url, { signal: request.signal });
            if (!response.ok || response.redirected) throw new Error('Catalogue unavailable');
            const page = new DOMParser().parseFromString(await response.text(), 'text/html');
            const next = page.getElementById('catalogue');
            if (!next || !next.querySelector('nav')) throw new Error('Catalogue missing');
            if (request.signal.aborted) return;

            const current = document.getElementById('catalogue');
            const hadFocus = current.contains(document.activeElement);
            current.replaceWith(next);
            if (saveHistory && location.href !== url) history.pushState(null, '', url);
            if (focusSearch) next.querySelector('#catalogue-search')?.focus({ preventScroll: true });
            else if (mobileControl === 'available') next.querySelector('[data-mobile-filters] input[type="checkbox"]')?.focus({ preventScroll: true });
            else if (mobileControl === 'filter') next.querySelector('[data-mobile-filters] select')?.focus({ preventScroll: true });
            else if (pagination) {
                next.querySelector('#catalogue-results-heading')?.focus({ preventScroll: true });
                next.scrollIntoView({ block: 'start' });
            }
            else if (hadFocus) next.querySelector('nav [aria-current="true"]')?.focus({ preventScroll: true });
        } catch (error) {
            if (!request.signal.aborted) location.assign(url);
        }
    }

    document.addEventListener('click', (event) => {
        const filter = event.target.closest('#catalogue nav a');
        if (!filter || event.defaultPrevented || event.button !== 0
            || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        updateCatalogue(filter.href, true, false, Boolean(filter.closest('[data-catalogue-pagination]')));
    });

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!form.matches('#catalogue form[role="search"]') || event.defaultPrevented) return;
        event.preventDefault();
        const url = new URL(form.action);
        url.search = new URLSearchParams(new FormData(form));
        updateCatalogue(url.href, true, true);
    });

    document.addEventListener('change', (event) => {
        const form = event.target.closest('[data-mobile-filters]');
        if (!form) return;
        const url = new URL(form.action);
        url.search = new URLSearchParams(new FormData(form));
        updateCatalogue(url.href, true, false, event.target.name);
    });

    window.addEventListener('popstate', () => updateCatalogue(location.href, false));

    document.querySelector('.student-hero-link').addEventListener('click', (event) => {
        if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey
            || !window.matchMedia('(prefers-reduced-motion: no-preference)').matches) {
            return;
        }

        event.preventDefault();
        const catalogue = document.getElementById('catalogue');
        if (location.hash !== '#catalogue') history.pushState(null, '', '#catalogue');
        catalogue.focus({ preventScroll: true });
        catalogue.scrollIntoView({ behavior: 'smooth' });
    });

    if (window.matchMedia('(hover: hover) and (pointer: fine)').matches && document.activeElement === document.body) {
        document.getElementById('catalogue-search').focus({ preventScroll: true });
    }
</script>
@endsection
