@extends('layouts.app')

@section('title', $title)

@section('content')
    <section class="max-w-xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-10" aria-labelledby="error-title">
        <p class="mb-3 text-sm font-semibold uppercase tracking-wider text-slate-500">Bibliothèque EST Fès</p>
        <h1 id="error-title" class="text-2xl font-semibold tracking-tight">{{ $title }}</h1>
        <p class="mt-4 leading-relaxed text-slate-600">{{ $message }}</p>
        <a href="{{ route('home') }}" class="mt-7 inline-flex rounded-lg bg-emerald-900 px-5 py-3 text-sm font-medium text-white hover:bg-emerald-800 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-emerald-800">Revenir à l’application</a>
    </section>
@endsection
