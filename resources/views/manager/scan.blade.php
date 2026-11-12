@extends('layouts.app')

@section('title', 'Emprunt et retour par QR')

@section('content')
    <section data-qr-scanner data-identify-url="{{ route('manager.scan.identify') }}" data-store-url="{{ route('manager.scan.store') }}" data-cancel-url="{{ route('manager.loans.cancel', ['loan' => '__LOAN__']) }}" data-manager-id="{{ auth()->user()->id }}" data-timezone="{{ config('library.timezone') }}" aria-labelledby="scan-title">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-800">Espace gérant · EST Fès</p>
        <h1 id="scan-title" class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">Emprunt ou retour, deux QR</h1>
        <p class="mt-4 max-w-2xl leading-relaxed text-slate-600">Scannez le QR de l’exemplaire et celui de la carte étudiante, dans l’ordre de votre choix. L’opération s’enregistre automatiquement dès que les deux QR sont reconnus.</p>
        <p class="mt-3 text-sm text-slate-500">{{ config('library.max_active_loans') }} exemplaires maximum par étudiant · Durée d’emprunt de {{ config('library.loan_duration_days') }} jours.</p>

        <noscript><p class="mt-6 rounded-lg bg-red-50 p-4 text-red-800">Activez JavaScript pour utiliser la caméra et scanner les QR codes.</p></noscript>

        <div class="mt-8 grid items-start gap-6 md:grid-cols-2">
            <div class="min-w-0 rounded-xl border border-slate-200 bg-white p-5 sm:p-6">
                <h2 class="font-semibold">Caméra</h2>
                <p class="mt-2 text-sm leading-relaxed text-slate-500">Placez un QR bien éclairé devant la caméra, puis présentez le second.</p>
                <div id="qr-reader" class="mt-5 min-h-56 overflow-hidden rounded-lg bg-slate-100"></div>
                <div class="mt-5 flex flex-wrap gap-3">
                    <button type="button" data-toggle-camera aria-pressed="false" disabled class="rounded-lg bg-emerald-900 px-4 py-3 text-sm font-semibold text-white hover:bg-emerald-800 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-emerald-800 disabled:cursor-not-allowed disabled:opacity-50">Activer la caméra</button>
                </div>
            </div>

            <div class="min-w-0 space-y-5">
                <section class="rounded-xl border border-slate-200 bg-white p-5 sm:p-6" aria-labelledby="pair-title">
                    <h2 id="pair-title" class="font-semibold">Les deux QR à présenter</h2>
                    <dl class="mt-5 space-y-4">
                        <div class="rounded-lg bg-slate-50 p-4"><dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Exemplaire</dt><dd data-copy-label class="mt-2 break-words text-sm text-slate-700">En attente du QR de l’exemplaire</dd></div>
                        <div class="rounded-lg bg-slate-50 p-4"><dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Étudiant</dt><dd data-student-label class="mt-2 break-words text-sm text-slate-700">En attente du QR de la carte</dd></div>
                    </dl>
                </section>

                <div role="status" aria-live="polite" aria-atomic="true" data-scan-status class="rounded-lg border border-slate-200 bg-white p-4 text-sm leading-relaxed text-slate-700">Activez la caméra pour commencer.</div>

                <section data-scan-result hidden class="rounded-xl border border-emerald-200 bg-emerald-50 p-5" aria-labelledby="result-title">
                    <h2 id="result-title" data-result-title class="font-semibold text-emerald-950"></h2>
                    <p data-result-student class="mt-3 break-words text-sm text-emerald-950"></p>
                    <p data-result-copy class="mt-1 break-words text-sm text-emerald-950"></p>
                    <p data-result-due class="mt-3 text-sm text-emerald-900"></p>
                    <button type="button" data-cancel-loan hidden class="mt-5 rounded-lg border border-red-300 bg-white px-4 py-2.5 text-sm font-semibold text-red-800 hover:bg-red-50 disabled:opacity-50">Annuler cet emprunt</button>
                </section>

                <div class="flex flex-wrap gap-3">
                    <button type="button" data-new-scan disabled class="rounded-lg border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50">Recommencer les scans</button>
                    <button type="button" data-retry-operation hidden class="rounded-lg bg-emerald-900 px-4 py-3 text-sm font-semibold text-white hover:bg-emerald-800 disabled:opacity-50">Vérifier l’opération</button>
                </div>
                <p class="text-xs leading-relaxed text-slate-500">Après l’enregistrement, lancez un nouveau scan pour l’opération suivante. Si l’exemplaire est emprunté par l’étudiant scanné, le même couple de QR enregistre son retour.</p>
            </div>
        </div>
    </section>
@endsection
