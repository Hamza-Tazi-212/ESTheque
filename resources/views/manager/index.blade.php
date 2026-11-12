@extends('layouts.app')

@section('title', 'Tableau de bord')

@section('content')
    <style>
        .dashboard-heading { font: bold clamp(2rem, 4vw, 3rem)/1.1 Georgia, serif; color:#104f3d; }
        .dashboard-date { margin-top:.5rem; color:#475569; }
        .dashboard-stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(min(100%, 12rem),1fr)); gap:1rem; margin-top:1.5rem; }
        .dashboard-stat { border:1px solid #cdded5; border-radius:1rem; background:#fff; padding:1.25rem; }
        .dashboard-stat dt { color:#475569; font-size:.8rem; font-weight:800; letter-spacing:.06em; text-transform:uppercase; }
        .dashboard-stat strong { display:block; margin-top:.6rem; font:bold 2.75rem/1 Georgia,serif; color:#104f3d; }
        .dashboard-stat span { display:block; margin-top:.35rem; font-size:.875rem; color:#475569; }
        .dashboard-stat--late { background:#fcf7f7; border-color:#fecaca; }
        .dashboard-stat--late strong { color:#b42318; }
        .dashboard-stat--today strong { color:#92400e; }
    </style>
    <section aria-labelledby="dashboard-title">
        <h1 id="dashboard-title" class="dashboard-heading">Bonjour {{ \Illuminate\Support\Str::before(trim(auth()->user()->name), ' ') }}</h1>
        <p class="dashboard-date"><time datetime="{{ $generatedAt->toIso8601String() }}">{{ $generatedAt->locale('fr')->translatedFormat('l j F Y') }} · mis à jour à {{ $generatedAt->format('H:i') }}</time></p>
        <dl class="dashboard-stats">
            <div class="dashboard-stat dashboard-stat--late">
                <dt>En retard</dt>
                <dd><strong>{{ $overdue }}</strong><span>Plus long : {{ $longest }} {{ $longest === 1 ? 'jour' : 'jours' }}</span></dd>
            </div>
            <div class="dashboard-stat dashboard-stat--today">
                <dt>À rendre aujourd’hui</dt>
                <dd><strong>{{ $dueToday }}</strong><span>avant la fermeture</span></dd>
            </div>
            <div class="dashboard-stat">
                <dt>À rendre demain</dt>
                <dd><strong>{{ $dueTomorrow }}</strong><span>à anticiper</span></dd>
            </div>
            <div class="dashboard-stat">
                <dt>Prêts en cours</dt>
                <dd><strong>{{ $active }}</strong><span>dont {{ $overdue }} en retard</span></dd>
            </div>
        </dl>
    </section>
    <style>
        .overdue-heading { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:.75rem; margin:2rem 0 .75rem; color:#b42318; font-weight:700; }
        .overdue-row { display:flex; flex-wrap:wrap; align-items:center; gap:.85rem; margin-bottom:.65rem; padding:1rem; border:1px solid #cdded5; border-radius:1rem; background:white; }
        .overdue-book { flex:1; min-width:10rem; color:#104f3d; overflow-wrap:anywhere; }
        .overdue-book p { color:#475569; font-size:.875rem; }
        .overdue-badge, .overdue-action { display:inline-flex; align-items:center; padding:.3rem .65rem; border:1px solid; border-radius:999px; font-size:.8rem; font-weight:700; }
        .overdue-badge { color:#b42318; border-color:#fecaca; background:#fef2f2; }
        .overdue-action { gap:.5rem; min-height:2.25rem; padding:.35rem .85rem; color:#104f3d; border-color:#a8c8b9; background:#fff; }
        .overdue-action svg { flex:none; }
        .overdue-action:focus-visible { outline:2px solid #34775f; outline-offset:3px; }
        .overdue-action[href]:hover { background:#d1fae5; }
        .overdue-action--bulk { background:#004e3e; color:white; border-color:#004e3e; border-radius:.6rem; padding:.55rem .85rem; }
        .overdue-action--bulk[href]:hover { background:#0b3b2d; }
        .overdue-action[aria-disabled="true"] { opacity:.55; }
        .today-heading { margin:2rem 0 .75rem; color:#92400e; font-weight:700; }
        .today-badge { color:#92400e; border-color:#fde68a; background:#fffbeb; }
    </style>
    <nav aria-label="Filtrer les prêts" style="display:flex; flex-wrap:wrap; gap:.65rem; margin-top:1.25rem;">
        @foreach(['tous' => 'Tous les prêts', 'retard' => 'En retard', 'rendus' => 'Rendus à temps'] as $key => $label)
            <a class="overdue-action" href="{{ route('manager.index', $key === 'tous' ? [] : ['prets' => $key]) }}"
                @if($filter === $key) aria-current="true" style="background:#d1fae5; border-color:#86efac;" @endif>
                @if($filter === $key)<span aria-hidden="true">✓</span>@endif
                {{ $label }}
            </a>
        @endforeach
    </nav>
    @if($filter !== 'rendus')
    <section aria-labelledby="overdue-list-title">
        <div class="overdue-heading">
            <h2 id="overdue-list-title">À traiter · {{ $lateStudentCount }} {{ $lateStudentCount === 1 ? 'étudiant' : 'étudiants' }} en retard</h2>
        </div>
        <aside style="display:flex; align-items:center; flex-wrap:wrap; gap:.8rem; margin-bottom:1rem; padding:.85rem 1.1rem; border-inline-start:3px solid #104f3d; background:#e6f2e9; color:#104f3d;">
            <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" style="flex:none;"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 7.5h.01"/></svg>
            <p style="flex:1 1 15rem;"><strong>Rappel automatique.</strong> Un e-mail regroupant les emprunts en retard de chaque étudiant est prévu chaque jour à 08:00 (heure du Maroc).</p>
            @if(count($reminderEmails))
                <a class="overdue-action overdue-action--bulk" href="mailto:?bcc={{ implode(',', array_map('rawurlencode', $reminderEmails)) }}&amp;{{ http_build_query(['subject' => 'Rappel ESThèque — retour de vos ouvrages', 'body' => 'Bonjour, un ou plusieurs de vos emprunts sont en retard. Merci de rapporter les exemplaires à la bibliothèque de l’EST Fès.'], '', '&', PHP_QUERY_RFC3986) }}">Relancer {{ count($reminderEmails) === 1 ? 'l’étudiant' : 'les '.count($reminderEmails) }}</a>
            @elseif($lateStudentCount > 0)
                <a class="overdue-action overdue-action--bulk" href="mailto:?subject=Rappel%20ESTh%C3%A8que&amp;body=Bonjour%2C%20merci%20de%20rapporter%20vos%20ouvrages%20en%20retard%20%C3%A0%20la%20biblioth%C3%A8que." title="Brouillon de test sans destinataire">Relancer les {{ $lateStudentCount }}</a>
            @endif
        </aside>
        @forelse($lateLoans as $item)
            <article class="overdue-row">
                <x-book-spine :title="$item['title']" />
                <div class="overdue-book">
                    <h3><strong>{{ $item['title'] }}</strong></h3>
                    <p>{{ $item['name'] }} · échéance {{ $item['due'] }}</p>
                </div>
                <span class="overdue-badge">●&nbsp; {{ $item['days'] }} j de retard</span>
                @if($item['email'])
                    <a class="overdue-action" aria-label="Relancer {{ $item['name'] }}" href="mailto:{{ rawurlencode($item['email']) }}?{{ http_build_query(['subject' => 'Rappel ESThèque — ouvrage en retard', 'body' => 'Bonjour '.$item['name'].', votre emprunt « '.$item['title'].' » est en retard. Merci de rapporter cet exemplaire à la bibliothèque de l’EST Fès.'], '', '&', PHP_QUERY_RFC3986) }}"><svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg> Relancer</a>
                @else
                    <a class="overdue-action" href="mailto:?{{ http_build_query(['subject' => 'Rappel ESThèque — ouvrage en retard', 'body' => 'Bonjour '.$item['name'].', votre emprunt « '.$item['title'].' » est en retard. Merci de rapporter cet exemplaire à la bibliothèque de l’EST Fès.'], '', '&', PHP_QUERY_RFC3986) }}" title="Brouillon de test sans destinataire"><svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg> Relancer</a>
                @endif
            </article>
        @empty
            <p>Aucun prêt en retard.</p>
        @endforelse
    </section>
    @endif

    @if($filter === 'tous')
    <section aria-labelledby="today-list-title">
        <h2 id="today-list-title" class="today-heading">À rendre aujourd’hui · {{ $dueToday }}</h2>
        @forelse($todayLoans as $item)
            <article class="overdue-row">
                <x-book-spine :title="$item['title']" />
                <div class="overdue-book">
                    <h3><strong>{{ $item['title'] }}</strong></h3>
                    <p>{{ $item['name'] }} · emprunté le {{ $item['borrowed'] }}</p>
                </div>
                <span class="overdue-badge today-badge">●&nbsp; Aujourd’hui</span>
            </article>
        @empty
            <p style="color:#475569;">Aucun ouvrage à rendre aujourd’hui.</p>
        @endforelse
    </section>
    @endif

    @if($filter !== 'retard')
        <section aria-labelledby="returns-list-title">
            <h2 id="returns-list-title" style="margin:2rem 0 .75rem; color:#104f3d; font-weight:700;">Rendus à temps · {{ $onTimeReturns->count() }}</h2>
            @forelse($onTimeReturns as $item)
                <article class="overdue-row">
                    <x-book-spine :title="$item['title']" />
                    <div class="overdue-book">
                        <h3><strong>{{ $item['title'] }}</strong></h3>
                        <p>{{ $item['name'] }} · rendu le {{ $item['returned'] }}</p>
                    </div>
                    <span class="overdue-badge" style="color:#104f3d; background:#ecfdf5; border-color:#a7f3d0;">●&nbsp; Rendu à temps</span>
                </article>
            @empty
                <p style="color:#475569;">Aucun retour à temps au cours des sept derniers jours.</p>
            @endforelse
        </section>
    @endif
@endsection
