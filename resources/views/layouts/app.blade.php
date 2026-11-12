<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="manifest" href="/bibliotheque.webmanifest">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Accueil') · ESThèque</title>
    <link rel="icon" type="image/png" href="/logo-bibliotheque-detoure.png">
    <link rel="apple-touch-icon" href="/logo-bibliotheque-detoure.png">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .catalogue-book-spine { display:flex; flex-direction:column; justify-content:space-between; width:2.8rem; height:4.4rem; padding:.55rem .3rem; border-radius:.28rem; background:#104f3d; color:#f2e7d7; text-align:center; font-size:.65rem; font-weight:700; }
        .catalogue-book-spine { position:relative; width:3.15rem; height:4.6rem; padding:.5rem .45rem .45rem .7rem; border-inline-start:.3rem solid #0b3b2d; text-align:start; box-shadow:0 3px 7px #104f3d22; }
        .catalogue-book-spine[data-tone="forest"] { background-color:#20362e; border-inline-start-color:#142920; }
        .catalogue-book-spine[data-tone="sage"] { background-color:#416657; border-inline-start-color:#304e41; }
        .catalogue-book-spine[data-tone="sand"] { background-color:#dfc9ab; border-inline-start-color:#b9a68a; color:#104f3d; }
        .catalogue-book-spine[data-tone="navy"] { background-color:#25454d; border-inline-start-color:#19333a; }
        .catalogue-book-spine[data-tone="sand"] .catalogue-spine-mark::before { border-color:#104f3d99; }
html { scrollbar-width: none; } html::-webkit-scrollbar { display: none; }
        .library-brand-logo {
            display: block;
            flex: none;
            width: clamp(3.5rem, 10vw, 4.25rem);
            height: auto;
            object-fit: contain;

        }

        .library-logout {
            padding: .5rem .85rem;
            border-radius: .65rem;
            background: #d1fae5;
            color: #104f3d;
            font-size: .875rem;
            font-weight: 600;
            cursor: pointer;
        }

        .library-logout:hover, .library-logout:focus-visible {
            background: #fff;
        }

        .library-logout:focus-visible {
            outline: 2px solid #d1fae5;
            outline-offset: 3px;
        }
        .manager-header-nav {
            display: flex;
            flex: 0 0 100%;
            justify-content: center;
            flex-wrap: wrap;
            gap: .45rem;
        }

        .manager-header-link {
            padding: .55rem .9rem;
            border-radius: 999px;
            color: #d1fae5;
            font-size: .875rem;
            font-weight: 600;
        }

        .manager-header-link:hover { background: #ffffff18; }
        .manager-header-link[aria-current="page"] { background: #d1fae5; color: #104f3d; }
        .manager-header-link:focus-visible { outline: 2px solid #d1fae5; outline-offset: 3px; }

        @media (min-width: 68rem) {
            .manager-header-row {
                display: grid;
                grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
            }

            .manager-header-row > div { grid-column: 3; grid-row: 1; justify-self: end; }
            .manager-header-nav { grid-column: 2; grid-row: 1; }
        }
        .catalogue-book-spine {
            background-image:linear-gradient(90deg,#0003,transparent 22%,#fff2 75%,#0002),
                repeating-linear-gradient(125deg,#fff1 0 1px,transparent 1px 4px);
            box-shadow:inset 0 1px 0 #fff3, inset -2px 0 0 #fff2, 1px 4px 8px #104f3d33;
        }
        .catalogue-book-spine::after {
            content:""; position:absolute; inset-block:3px; right:2px; width:2px;
            border-radius:1px; background:#f2e7d780;
        }
        .catalogue-spine-mark { line-height:1.05; }
        .catalogue-spine-mark::before { content:""; display:block; width:1.25rem; margin-bottom:.45rem; border-top:1px solid #d1fae599; }
    </style>
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-900 antialiased">
    <header style="position: sticky; top: 0; z-index: 40; background: #104f3d; color: #fff;" class="@unless(request()->routeIs('student.index')) border-b border-emerald-900 @endunless">
        <div class="flex w-full flex-wrap items-center justify-between gap-3 px-5 py-2 sm:px-8 @if(auth()->user()?->role === \App\Enums\UserRole::Manager) manager-header-row @endif">
            <a href="{{ route('home') }}" class="font-semibold tracking-tight text-white" style="display: inline-flex; align-items: center; gap: 10px;">
                <img src="{{ asset('logo-bibliotheque-detoure.png') }}" alt="Emblème de la bibliothèque EST Fès" width="1280" height="1280" class="library-brand-logo">
                <span style="font-size: 1.25rem; text-shadow: 0 0 8px rgba(209,250,229,.22);"><span style="color: #d1fae5;">EST</span><span style="color: #F2E7D7;">hèque</span></span>
            </a>
            @auth
                <div class="ml-auto flex flex-wrap items-center gap-3">
                    @if(session('google_avatar'))
                        <img src="{{ session('google_avatar') }}" alt="" width="44" height="44" referrerpolicy="no-referrer" style="width: 44px; height: 44px; flex-shrink: 0; border-radius: 50%; object-fit: cover;">
                    @else
                        <span aria-hidden="true" style="display: inline-grid; place-items: center; width: 44px; height: 44px; flex-shrink: 0; border-radius: 50%; background: #d1fae5; color: #064e3b;">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr(auth()->user()->name, 0, 1)) }}</span>
                    @endif
                    <div style="display: flex; flex-direction: column; line-height: 1.3; gap: 2px;">
                        <span style="font-size: 16px; font-weight: 600;" class="text-white">{{ auth()->user()->name }}</span>
                        <span style="font-size: 13px; font-weight: 600; color: #d1fae5;">{{ auth()->user()->role === \App\Enums\UserRole::Manager ? 'Gestionnaire' : 'Étudiant' }}</span>
                    </div>
                    <form style="margin-inline-start: 6px;" method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="library-logout">Se déconnecter</button>
                    </form>
                </div>
            @endauth
            @if(auth()->user()?->role === \App\Enums\UserRole::Manager)
                <nav aria-label="Espace gérant" class="manager-header-nav">
                    <a href="{{ route('manager.index') }}" @if(request()->routeIs('manager.index', 'manager.overdue.*')) aria-current="page" @endif class="manager-header-link">Tableau de bord</a>
                    <a href="{{ route('manager.scan') }}" @if(request()->routeIs('manager.scan*')) aria-current="page" @endif class="manager-header-link">Scanner les QR</a>
                </nav>
            @endif
        </div>
    </header>
    @yield('hero')
    <main class="mx-auto max-w-5xl px-5 py-12 sm:px-8 sm:py-20">
        @yield('content')
    </main>
    <footer style="border-top:1px solid #d5e3d9; background:#f0f5ef; padding:25px 0;">
        <div style="width:min(1200px, calc(100% - clamp(36px, 6vw, 72px))); margin:auto; display:flex; flex-wrap:wrap; justify-content:space-between; align-items:center; gap:8px 18px; font-size:11px; color:#567566;">
            <span><strong style="color:#104f3d;">ESThèque</strong> · Bibliothèque de l’EST Fès</span>
            <span>Projet universitaire · EST Fès · 2026</span>
        </div>
    </footer>
</body>
</html>
