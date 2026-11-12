@extends('layouts.app')

@section('title', $title)

@section('content')
    <section class="mx-auto max-w-xl rounded-2xl border border-slate-200 bg-white p-6 text-center shadow-sm sm:p-10" aria-labelledby="error-title">
        <div class="mx-auto mb-5 grid size-12 place-items-center rounded-full bg-brand-mint text-brand-green" aria-hidden="true">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="9"/>
                <path d="M12 8v4m0 4h.01"/>
            </svg>
        </div>
        <h1 id="error-title" class="text-3xl font-semibold tracking-tight text-brand-green sm:text-4xl" style="font-family: Georgia, serif">{{ $title }}</h1>
        <p class="mx-auto mt-4 max-w-md break-words leading-relaxed text-slate-600">{{ \Illuminate\Support\Str::ucfirst(\Illuminate\Support\Str::after($message, $title.' : ')) }}</p>
        <x-action :href="route('home')" class="mt-6">Revenir à l’application</x-action>
    </section>
@endsection

