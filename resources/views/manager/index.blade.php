@extends('layouts.app')

@section('title', 'Suivi des retards')

@section('content')
    <section aria-labelledby="dashboard-title">
        <div class="flex flex-wrap items-end justify-between gap-5">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-800">Espace gérant · EST Fès</p>
                <h1 id="dashboard-title" class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">Le suivi des retards</h1>
                <p class="mt-4 max-w-xl leading-relaxed text-slate-600">Les exemplaires à récupérer et les étudiants concernés, en un regard.</p>
            </div>
            <p class="text-xs leading-relaxed text-slate-500">Consulté le {{ $generatedAt->setTimezone(config('library.timezone'))->format('d/m/Y à H:i') }}</p>
        </div>

        <dl class="mt-8 grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-white p-6">
                <dt class="text-sm text-slate-500">Étudiants en retard</dt>
                <dd class="mt-3 text-4xl font-semibold tracking-tight text-emerald-950">{{ $statistics['students'] }}</dd>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-6">
                <dt class="text-sm text-slate-500">Exemplaires en retard</dt>
                <dd class="mt-3 text-4xl font-semibold tracking-tight text-emerald-950">{{ $statistics['copies'] }}</dd>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-6">
                <dt class="text-sm text-slate-500">Retard le plus long</dt>
                <dd class="mt-3 text-4xl font-semibold tracking-tight text-emerald-950">{{ $statistics['maxDaysLate'] }} <span class="text-base font-normal text-slate-500">{{ $statistics['maxDaysLate'] === 1 ? 'jour' : 'jours' }}</span></dd>
            </div>
        </dl>

        <p class="mt-5 rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm leading-relaxed text-emerald-950">Un email regroupant les exemplaires en retard de chaque étudiant est prévu chaque jour à {{ config('library.overdue_reminder_time') }}, heure du Maroc.</p>

        @if($statistics['students'] > 0)
            <div class="mt-8 grid gap-5 md:grid-cols-2">
                <section class="min-w-0 rounded-xl border border-slate-200 bg-white p-5 sm:p-6" aria-labelledby="student-chart-title">
                    <h2 id="student-chart-title" class="font-semibold">Étudiants par niveau de retard</h2>
                    <p class="mt-1 text-xs leading-relaxed text-slate-500">Chaque étudiant est classé selon son retard le plus élevé.</p>
                    <div class="relative mt-5 h-64">
                        <canvas id="overdue-students-chart" role="img" aria-label="Répartition des étudiants par niveau de retard. Les chiffres sont aussi présentés dans le tableau ci-dessous.">Les détails de chaque étudiant sont disponibles dans le tableau ci-dessous.</canvas>
                    </div>
                </section>
                <section class="min-w-0 rounded-xl border border-slate-200 bg-white p-5 sm:p-6" aria-labelledby="copy-chart-title">
                    <h2 id="copy-chart-title" class="font-semibold">Exemplaires par niveau de retard</h2>
                    <p class="mt-1 text-xs leading-relaxed text-slate-500">Un exemplaire compte une fois dans son niveau de retard.</p>
                    <div class="relative mt-5 h-64">
                        <canvas id="overdue-copies-chart" role="img" aria-label="Répartition des exemplaires par niveau de retard. Consultez la fiche d’un étudiant pour voir ses exemplaires.">Les exemplaires sont détaillés dans la fiche de chaque étudiant.</canvas>
                    </div>
                </section>
            </div>
            <script type="application/json" data-overdue-chart-data>{!! \Illuminate\Support\Js::encode($charts) !!}</script>

            <section class="mt-10" aria-labelledby="late-students-title">
                <h2 id="late-students-title" class="mb-4 text-lg font-semibold">Étudiants concernés</h2>
                <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
                    <table class="w-full text-left text-sm">
                        <caption class="sr-only">Étudiants en retard, du retard le plus élevé au moins élevé.</caption>
                        <thead class="border-b border-slate-200 bg-slate-50 text-xs text-slate-500">
                            <tr>
                                <th scope="col" class="px-5 py-4 font-medium">Étudiant</th>
                                <th scope="col" class="px-5 py-4 font-medium">Exemplaires en retard</th>
                                <th scope="col" class="px-5 py-4 font-medium">Retard maximal</th>
                                <th scope="col" class="px-5 py-4 font-medium">Niveau</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($students as $item)
                                <tr class="hover:bg-slate-50">
                                    <th scope="row" class="px-5 py-5 font-normal">
                                        <a href="{{ route('manager.overdue.show', $item->student->id) }}" class="break-words font-semibold text-emerald-900 underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-emerald-800">{{ $item->student->name }}</a>
                                        <span class="mt-1 block break-all text-xs text-slate-500">{{ $item->student->email }}</span>
                                    </th>
                                    <td class="px-5 py-5">{{ count($item->loans) }}</td>
                                    <td class="px-5 py-5 whitespace-nowrap">{{ $item->maxDaysLate }} {{ $item->maxDaysLate === 1 ? 'jour' : 'jours' }}</td>
                                    <td class="px-5 py-5 whitespace-nowrap"><x-overdue-badge :level="$item->level" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @else
            <div class="mt-8 rounded-xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center">
                <p class="text-lg font-semibold text-slate-800">Aucun retard de retour</p>
                <p class="mt-2 text-sm text-slate-500">Aucun étudiant n’a actuellement d’exemplaire à rendre en retard.</p>
            </div>
        @endif
    </section>
@endsection
