@extends('layouts.app')

@section('title', 'Emprunt et retour par QR')

@section('content')

    <style>
        .scan-workspace { display:grid; gap:1.5rem; align-items:start; color:#104f3d; }
        .scan-camera-card, .scan-pair-card { min-width:0; padding:1rem; border:1px solid #cdded5; border-radius:1rem; background:#fff; }
        .scan-camera-screen { position:relative; overflow:hidden; border-radius:.8rem; background:#0c2119; color:#d1fae5; }
        .scan-camera-viewport {
            --preview-height: clamp(14rem, 28vw, 21rem);
            height: var(--preview-height);
            container-type: inline-size;
            display: grid;
            place-items: center;
            overflow: hidden;
        }
        .scan-camera-viewport #qr-reader { position:relative; max-width:100%; }
        .scan-camera-viewport #qr-reader video {
            display:block;
            width:auto !important;
            height:auto;
            max-width:100cqw;
            max-height:var(--preview-height);
        }
        .scan-camera-indicator { position:absolute; inset: .75rem auto auto .75rem; z-index:2; padding:.3rem .65rem; border-radius:999px; background:#243d33; font-size:.75rem; font-weight:700; pointer-events:none; }
        .scan-camera-indicator::before { content:""; display:inline-block; width:.4rem; height:.4rem; margin-inline-end:.35rem; border-radius:50%; background:currentColor; }
        .scan-camera-on { display:none; }
        .scan-camera-guide { position:absolute; inset:0 0 3rem; display:grid; place-items:center; pointer-events:none; }
        .scan-camera-guide span { position:relative; width:min(38%, 9rem); aspect-ratio:1; border:3px solid #6ee7b7; border-radius:1rem; }
        .scan-camera-guide span::after { content:""; position:absolute; inset:50% 12% auto; border-top:2px solid #34b88a; }
        .scan-camera-card:has([data-toggle-camera][aria-pressed="true"]) .scan-camera-on { display:inline; }
        .scan-camera-card:has([data-toggle-camera][aria-pressed="true"]) :is(.scan-camera-off, .scan-camera-guide) { display:none; }
        .scan-camera-status { position:relative; padding:.75rem 1rem; text-align:center; font-size:.875rem; }
        .scan-camera-actions { display:flex; flex-wrap:wrap; gap:.65rem; margin-top:1rem; }
        .scan-camera-actions button { border:1px solid #a8c8b9; border-radius:999px; padding:.55rem .9rem; background:#fff; font-size:.875rem; font-weight:700; }
        .scan-camera-actions button:hover:not(:disabled) { background:#d1fae5; }
        .scan-camera-actions button:focus-visible { outline:2px solid #34775f; outline-offset:3px; }
        .scan-camera-actions button:disabled { opacity:.55; }
        .scan-side { min-width:0; }
        .scan-pair { display:grid; gap:.85rem; }
        .scan-pair-card { display:grid; grid-template-columns:auto minmax(0,1fr) auto; align-items:center; gap:clamp(.55rem,1.35vw,1rem); }
        .scan-pair-card dt { font-weight:700; }
        .scan-pair-card dd { margin-top:.25rem; color:#475569; font-size:.875rem; overflow-wrap:anywhere; }
        .scan-book-mark, .scan-student-mark { display:grid; place-content:center; width:2.8rem; height:3.9rem; border-radius:.35rem; }
        .scan-book-mark { border-inline-start:4px solid #0b3b2d; background:#104f3d; color:#d1fae5; font-size:.65rem; font-weight:700; }
        .scan-pair-card[data-waiting] { border:2px dashed #79a891; background:#f3f9f5; }
        .scan-pair-card[data-waiting] .scan-book-mark { border:2px dashed #79a891; background:transparent; color:transparent; }
        .scan-card-badge { justify-self:end; white-space:nowrap; padding:.2rem .55rem; border:1px solid #a7f3d0; border-radius:999px; background:#ecfdf5; font-size:.75rem; font-weight:600; }
        .scan-pair-card[data-waiting] .scan-card-badge { background:#f1f5f9; color:#475569; border-color:#cbd5e1; }
        .scan-card-waiting, .scan-pair-card[data-waiting] .scan-card-ready { display:none; }
        .scan-pair-card[data-waiting] .scan-card-waiting { display:inline; }
        .scan-student-mark { border:2px dashed #79a891; }
        .scan-card-badge::before { content:""; display:inline-block; width:.375rem; height:.375rem; margin-inline-end:.4rem; vertical-align:.12em; border-radius:50%; background:currentColor; }
        .scan-waiting-copy { display:none; }
        .scan-pair-card[data-waiting] .scan-waiting-copy { display:block; }
        .scan-pair-card[data-waiting] dd:not(.scan-waiting-copy) { display:none; }
        @media (max-width: 25rem) {
            .scan-pair-card { grid-template-columns:auto minmax(0,1fr); }
            .scan-card-badge { grid-column:2; justify-self:start; }
        }
        .scan-rules {
            display:flex; align-items:center; gap:1rem; min-height:8.65rem;
            border-inline-start:3px solid #104f3d; background:#e6f2e9;
            padding:1.25rem; font-size:1rem; line-height:1.5;
        }
        .scan-rules svg { flex:none; }
        @media (min-width: 48rem) { .scan-workspace { grid-template-columns:minmax(0,1.3fr) minmax(0,1fr); } }
    </style>
    <section data-qr-scanner data-identify-url="{{ route('manager.scan.identify') }}" data-store-url="{{ route('manager.scan.store') }}" data-cancel-url="{{ route('manager.loans.cancel', ['loan' => '__LOAN__']) }}" data-manager-id="{{ auth()->user()->id }}" data-timezone="{{ config('library.timezone') }}" aria-labelledby="scan-title">
        <h1 id="scan-title" class="mb-6 text-2xl font-semibold text-emerald-950">Scanner les QR</h1>
        <noscript><p class="mt-6 rounded-lg bg-red-50 p-4 text-red-800">Activez JavaScript pour utiliser la caméra et scanner les QR codes.</p></noscript>

        <div class="scan-workspace">
            <div class="scan-camera-card">
                <div class="scan-camera-screen">
                    <div class="scan-camera-indicator">
                        <span class="scan-camera-off">Caméra désactivée</span>
                        <span class="scan-camera-on">Caméra active</span>
                    </div>
                    <div class="scan-camera-viewport">
                        <div id="qr-reader"></div>
                    </div>
                    <div class="scan-camera-guide" aria-hidden="true"><span></span></div>
                    <div role="status" aria-live="polite" aria-atomic="true" data-scan-status class="scan-camera-status">Activez la caméra pour commencer.</div>
                </div>
                <div class="scan-camera-actions">
                    <button type="button" data-toggle-camera aria-pressed="false" disabled>Activer la caméra</button>
                    <button type="button" data-new-scan disabled>Recommencer les scans</button>
                </div>
            </div>

            <div class="scan-side space-y-5">
                <section aria-label="Les deux QR à présenter">
                    <dl class="scan-pair">
                        <div class="scan-pair-card" data-scan-card data-waiting>
                            <span class="scan-book-mark" aria-hidden="true">EST<br>FÈS</span>
                            <div>
                                <dt>Exemplaire</dt>
                                <dd data-copy-label>En attente du QR de l’exemplaire</dd>
                                <dd class="scan-waiting-copy">Présentez le QR de l’exemplaire</dd>
                            </div>
                            <span class="scan-card-badge">
                                <span class="scan-card-waiting">En attente</span>
                                <span class="scan-card-ready">Exemplaire scanné</span>
                            </span>
                        </div>
                        <div class="scan-pair-card" data-scan-card data-waiting>
                            <span class="scan-student-mark" aria-hidden="true"></span>
                            <div>
                                <dt>Carte étudiante</dt>
                                <dd data-student-label>En attente du QR de la carte</dd>
                                <dd class="scan-waiting-copy">Présentez le QR de la carte</dd>
                            </div>
                            <span class="scan-card-badge">
                                <span class="scan-card-waiting">En attente</span>
                                <span class="scan-card-ready">Carte scannée</span>
                            </span>
                        </div>
                    </dl>
                </section>

                <aside class="scan-rules">
                    <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                        <circle cx="12" cy="12" r="9"/>
                        <path d="M12 11v5M12 7.5h.01"/>
                    </svg>
                    <p><strong>Règles.</strong> {{ config('library.max_active_loans') }} exemplaires maximum par étudiant · {{ config('library.loan_duration_days') }} jours d’emprunt.</p>
                </aside>

                <section data-scan-result hidden class="rounded-xl border border-emerald-200 bg-emerald-50 p-5" aria-labelledby="result-title">
                    <h2 id="result-title" data-result-title class="font-semibold text-emerald-950"></h2>
                    <p data-result-student class="mt-3 break-words text-sm text-emerald-950"></p>
                    <p data-result-copy class="mt-1 break-words text-sm text-emerald-950"></p>
                    <p data-result-due class="mt-3 text-sm text-emerald-900"></p>
                    <button type="button" data-cancel-loan hidden class="mt-5 rounded-lg border border-red-300 bg-white px-4 py-2.5 text-sm font-semibold text-red-800 hover:bg-red-50 disabled:opacity-50">Annuler cet emprunt</button>
                </section>

                <div class="flex flex-wrap gap-3">
                    <button type="button" data-retry-operation hidden class="rounded-lg bg-emerald-900 px-4 py-3 text-sm font-semibold text-white hover:bg-emerald-800 disabled:opacity-50">Vérifier l’opération</button>
                </div>
                <p class="text-xs leading-relaxed text-slate-500">Après l’enregistrement, lancez un nouveau scan pour l’opération suivante. Si l’exemplaire est emprunté par l’étudiant scanné, le même couple de QR enregistre son retour.</p>
            </div>
        </div>
    </section>

    <script>
        document.querySelectorAll('[data-scan-card]').forEach((card) => {
            const label = card.querySelector('dd');
            const waitingLabel = label.textContent.trim();
            const observer = new MutationObserver(() => {
                card.toggleAttribute('data-waiting', label.textContent.trim() === waitingLabel);
            });
            observer.observe(label, { childList: true, characterData: true, subtree: true });
        });
    </script>
@endsection
