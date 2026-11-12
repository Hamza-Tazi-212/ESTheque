<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="manifest" href="/bibliotheque.webmanifest">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Bibliothèque') · EST Fès</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-900 antialiased">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-3 px-5 py-5 sm:px-8">
            <a href="{{ route('home') }}" class="font-semibold tracking-tight text-emerald-900">Bibliothèque <span class="text-slate-500">/ EST Fès</span></a>
            @auth
                <span class="text-sm text-slate-600">{{ auth()->user()->name }}</span>
            @endauth
        </div>
        @if(auth()->user()?->role === \App\Enums\UserRole::Manager)
            <nav aria-label="Espace gérant" class="mx-auto flex max-w-5xl gap-6 px-5 pb-4 text-sm sm:px-8">
                <a href="{{ route('manager.index') }}" @if(request()->routeIs('manager.index', 'manager.overdue.*')) aria-current="page" @endif class="font-medium text-emerald-900 underline-offset-4 hover:underline">Suivi des retards</a>
                <a href="{{ route('manager.scan') }}" @if(request()->routeIs('manager.scan*')) aria-current="page" @endif class="font-medium text-emerald-900 underline-offset-4 hover:underline">Scanner les QR</a>
            </nav>
        @endif
    </header>
    <main class="mx-auto max-w-5xl px-5 py-12 sm:px-8 sm:py-20">
        @yield('content')
    </main>
</body>
</html>
