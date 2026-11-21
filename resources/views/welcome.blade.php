@extends('layouts.app')
@section('title', 'HUQUQ AI Academy')
@section('content')
<section class="mx-auto max-w-4xl rounded-3xl border border-blue-100 bg-white px-6 py-16 sm:px-12">
    <p class="text-sm font-semibold uppercase tracking-widest text-blue-700">HUQUQ AI Academy</p>
    <h1 class="mt-6 text-4xl font-semibold leading-tight sm:text-5xl">{{ __('Huquqiy bilimdan amaliy ko‘nikmaga') }}</h1>
    <p class="mt-6 max-w-2xl text-lg leading-relaxed text-slate-600">{{ __('Darslar, huquqiy kazuslar, mentor yordami va AI Ustoz bilan bosqichma-bosqich o‘rganing.') }}</p>
    <a href="{{ auth()->check() ? route('dashboard') : route('login') }}" class="mt-8 inline-block rounded-xl bg-blue-700 px-6 py-3 font-medium text-white">{{ __('O‘rganishni boshlash') }}</a>
    <div class="mt-12 grid gap-6 sm:grid-cols-3">@foreach (['O‘qituvchi darslari', 'Mentor bilan muloqot', 'AI bilan mustahkamlash'] as $label)<div class="rounded-xl bg-slate-50 p-5 font-medium">{{ __($label) }}</div>@endforeach</div>
</section>
@endsection
