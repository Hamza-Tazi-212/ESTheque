@extends('layouts.app')

@section('title', 'Tableau de bord')

@section('content')
    <style>
        .dashboard-heading { font: bold clamp(2rem, 4vw, 3rem)/1.1 Georgia, serif; color:var(--color-brand-green); }
        .dashboard-date { margin-top:.5rem; color:#475569; }
        .dashboard-stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(min(100%, 12rem),1fr)); gap:1rem; margin-top:1.5rem; }
        .dashboard-stat { border:1px solid var(--color-library-border); border-radius:1rem; background:#fff; padding:1.25rem; }
        .dashboard-stat dt { color:#475569; font-size:.8rem; font-weight:800; letter-spacing:.06em; text-transform:uppercase; }
        .dashboard-stat strong { display:block; margin-top:.6rem; font:bold 2.75rem/1 Georgia,serif; color:var(--color-brand-green); }
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
        .overdue-row { display:flex; flex-wrap:wrap; align-items:center; gap:1rem; margin-bottom:.75rem; padding:1rem; border:1px solid var(--color-library-border); border-radius:.9rem; background:white; }
        .overdue-book { flex:1; min-width:min(10rem, 100%); color:var(--color-brand-green); overflow-wrap:anywhere; }
        .overdue-book p { margin-top:.25rem; color:#475569; font-size:.875rem; }
        .today-heading { margin:2rem 0 .75rem; color:#92400e; font-weight:700; }
    </style>
    <nav aria-label="Filtrer les prêts" style="display:flex; flex-wrap:wrap; gap:.65rem; margin-top:1.25rem;">
        @foreach(['tous' => 'Tous les prêts', 'retard' => 'En retard', 'rendus' => 'Rendus à temps'] as $key => $label)
            <x-filter-chip :href="route('manager.index', $key === 'tous' ? [] : ['prets' => $key])" :selected="$filter === $key">{{ $label }}</x-filter-chip>
        @endforeach
    </nav>
    @if($filter !== 'rendus')
    <section aria-labelledby="overdue-list-title">
        <div class="overdue-heading">
            <h2 id="overdue-list-title">À traiter · {{ $lateStudentCount }} {{ $lateStudentCount === 1 ? 'étudiant' : 'étudiants' }} en retard</h2>
        </div>
        <x-info-banner class="mb-4">
            <strong>Rappel automatique.</strong> Un e-mail regroupant les emprunts en retard de chaque étudiant est prévu chaque jour à 08:00 (heure du Maroc).
            <x-slot:action>
            @if(count($reminderEmails))
                <x-action variant="primary" :href="'mailto:?bcc='.implode(',', array_map('rawurlencode', $reminderEmails)).'&'.http_build_query(['subject' => 'Rappel ESThèque — retour de vos ouvrages', 'body' => 'Bonjour, un ou plusieurs de vos emprunts sont en retard. Merci de rapporter les exemplaires à la bibliothèque de l’EST Fès.'], '', '&', PHP_QUERY_RFC3986)">Relancer {{ count($reminderEmails) === 1 ? 'l’étudiant' : 'les '.count($reminderEmails) }}</x-action>
            @elseif($lateStudentCount > 0)
                <x-action variant="primary" :href="'mailto:?subject=Rappel%20ESTh%C3%A8que&body=Bonjour%2C%20merci%20de%20rapporter%20vos%20ouvrages%20en%20retard%20%C3%A0%20la%20biblioth%C3%A8que.'" title="Brouillon de test sans destinataire">Relancer les {{ $lateStudentCount }}</x-action>
            @endif
            </x-slot:action>
        </x-info-banner>
        @forelse($lateLoans as $item)
            <article class="overdue-row">
                <x-book-spine :title="$item['title']" />
                <div class="overdue-book">
                    <h3><strong>{{ $item['title'] }}</strong></h3>
                    <p>{{ $item['name'] }} · échéance {{ $item['due'] }}</p>
                </div>
                <x-status-badge tone="error">{{ $item['days'] }} j de retard</x-status-badge>
                @if($item['email'])
                    <x-action variant="secondary" aria-label="Relancer {{ $item['name'] }}" :href="'mailto:'.rawurlencode($item['email']).'?'.http_build_query(['subject' => 'Rappel ESThèque — ouvrage en retard', 'body' => 'Bonjour '.$item['name'].', votre emprunt « '.$item['title'].' » est en retard. Merci de rapporter cet exemplaire à la bibliothèque de l’EST Fès.'], '', '&', PHP_QUERY_RFC3986)"><svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg> Relancer</x-action>
                @else
                    <x-action variant="secondary" :href="'mailto:?'.http_build_query(['subject' => 'Rappel ESThèque — ouvrage en retard', 'body' => 'Bonjour '.$item['name'].', votre emprunt « '.$item['title'].' » est en retard. Merci de rapporter cet exemplaire à la bibliothèque de l’EST Fès.'], '', '&', PHP_QUERY_RFC3986)" title="Brouillon de test sans destinataire"><svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg> Relancer</x-action>
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
                <x-status-badge tone="warning">Aujourd’hui</x-status-badge>
            </article>
        @empty
            <p style="color:#475569;">Aucun ouvrage à rendre aujourd’hui.</p>
        @endforelse
    </section>
    @endif

    @if($filter !== 'retard')
        <section aria-labelledby="returns-list-title">
            <h2 id="returns-list-title" style="margin:2rem 0 .75rem; color:var(--color-brand-green); font-weight:700;">Rendus à temps · {{ $onTimeReturns->count() }}</h2>
            @forelse($onTimeReturns as $item)
                <article class="overdue-row">
                    <x-book-spine :title="$item['title']" />
                    <div class="overdue-book">
                        <h3><strong>{{ $item['title'] }}</strong></h3>
                        <p>{{ $item['name'] }} · rendu le {{ $item['returned'] }}</p>
                    </div>
                    <x-status-badge tone="success">Rendu à temps</x-status-badge>
                </article>
            @empty
                <p style="color:#475569;">Aucun retour à temps au cours des sept derniers jours.</p>
            @endforelse
        </section>
    @endif
@endsection
