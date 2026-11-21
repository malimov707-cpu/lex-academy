@extends('layouts.public')

@section('title', __('Platforma haqida') . ' — HUQUQ AI Academy')

@section('content')
    <section class="edu-public-hero !min-h-[40vh]">
        <div class="edu-public-hero-bg" aria-hidden="true"></div>
        <div class="edu-public-hero-tint" aria-hidden="true"></div>
        <div class="edu-public-container relative z-10 py-20 max-w-3xl">
            <h1 class="edu-public-hero-title !text-4xl">{{ __('Platforma haqida') }}</h1>
            <p class="edu-public-hero-lead">
                {{ __('Legal EDU — huquqiy bilimlarni tizimli o‘rgatish, nazariyani amaliyot bilan bog‘lash va har bir o‘quvchining individual rivojlanishini kuzatishga yo‘naltirilgan zamonaviy ta’lim platformasi.') }}
            </p>
        </div>
    </section>

    <section class="edu-public-section">
        <div class="edu-public-container max-w-3xl space-y-6">
            <div class="edu-public-mini-card !text-left">
                <h2 class="edu-public-h2 !text-xl">{{ __('Bizning yondashuv') }}</h2>
                <p class="mt-3">{{ __('Har bir kurs modullar, darslar va topshiriqlardan iborat to‘liq o‘quv rejasi asosida tuziladi. Nazariy materiallar haqiqiy huquqiy keyslar bilan mustahkamlanadi.') }}</p>
            </div>
            <div class="edu-public-mini-card !text-left">
                <h2 class="edu-public-h2 !text-xl">{{ __('AI Ustoz va mentor') }}</h2>
                <p class="mt-3">{{ __('AI Ustoz mavzularni tushuntiradi va savollarga javob beradi; uy vazifalari mentor tomonidan tekshirilib baholanadi.') }}</p>
            </div>
            <div class="edu-public-mini-card !text-left">
                <h2 class="edu-public-h2 !text-xl">{{ __('Rivojlanish kuzatuvi') }}</h2>
                <p class="mt-3">{{ __('O‘zlashtirish statistikasi, baholar va individual tavsiyalar shaxsiy kabinetda aks etadi.') }}</p>
            </div>
            <div class="text-center">
                <a href="{{ route('register') }}" class="edu-btn-primary !py-3 !px-6">{{ __('Ta’limni boshlash') }}</a>
            </div>
        </div>
    </section>
@endsection
