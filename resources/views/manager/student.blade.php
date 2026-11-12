@extends('layouts.app')

@section('title', 'Retards de '.$report->student->name)

@section('content')
    <a href="{{ route('manager.index') }}" class="inline-flex items-center gap-2 text-sm font-medium text-emerald-800 underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-emerald-800"><span aria-hidden="true">←</span> Retour au suivi des retards</a>

    <section class="mt-8" aria-labelledby="student-name">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-800">Étudiant en retard</p>
        <h1 id="student-name" class="mt-3 break-words text-3xl font-semibold tracking-tight sm:text-4xl">{{ $report->student->name }}</h1>
        <p class="mt-3 break-all text-slate-500">{{ $report->student->email }}</p>
        <div class="mt-5 flex flex-wrap items-center gap-3">
            <x-overdue-badge :level="$report->level" />
            <span class="text-sm text-slate-500">Retard maximal : {{ $report->maxDaysLate }} {{ $report->maxDaysLate === 1 ? 'jour' : 'jours' }}</span>
        </div>

        <h2 class="mt-10 mb-4 text-lg font-semibold">{{ count($report->loans) }} {{ count($report->loans) === 1 ? 'exemplaire à rendre' : 'exemplaires à rendre' }}</h2>
        <div class="space-y-4">
            @foreach($report->loans as $loan)
                <article class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6" aria-labelledby="loan-title-{{ $loan->id }}">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="min-w-0">
                            <h3 id="loan-title-{{ $loan->id }}" class="break-words text-lg font-semibold">{{ $loan->title }}</h3>
                            <p class="mt-1 break-words text-sm text-slate-500">{{ $loan->author }}</p>
                        </div>
                        <x-overdue-badge :level="$loan->level" />
                    </div>
                    <dl class="mt-6 grid gap-5 border-t border-slate-100 pt-5 sm:grid-cols-2">
                        <div><dt class="text-xs text-slate-500">Exemplaire</dt><dd class="mt-1 break-all text-sm font-medium">{{ $loan->copyQrCode }}</dd></div>
                        <div><dt class="text-xs text-slate-500">Retard</dt><dd class="mt-1 text-sm font-medium">{{ $loan->daysLate }} {{ $loan->daysLate === 1 ? 'jour' : 'jours' }}</dd></div>
                        <div><dt class="text-xs text-slate-500">Emprunté le</dt><dd class="mt-1 text-sm font-medium">{{ $loan->borrowedAt->setTimezone(config('library.timezone'))->format('d/m/Y à H:i') }}</dd></div>
                        <div><dt class="text-xs text-slate-500">À rendre avant le</dt><dd class="mt-1 text-sm font-medium">{{ $loan->dueAt->setTimezone(config('library.timezone'))->format('d/m/Y à H:i') }}</dd></div>
                    </dl>
                </article>
            @endforeach
        </div>
    </section>
@endsection
