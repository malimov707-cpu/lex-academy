@extends('layouts.public')

@section('title', __('Savol-javob') . ' — HUQUQ AI Academy')

@section('content')
    <section class="edu-public-section">
        <div class="edu-public-container max-w-3xl py-12">
            <h1 class="edu-public-h2 text-center">{{ __('Ko‘p so‘raladigan savollar') }}</h1>
            <div class="mt-10 space-y-3">
                @foreach ([
                    __('Legal EDU nima?') => __('Legal EDU — huquqiy bilimlarni tizimli o‘rgatuvchi, AI Ustoz va mentor qo‘llab-quvvatlashiga ega zamonaviy ta’lim platformasi.'),
                    __('Qanday kurslarda o‘qish mumkin?') => __('Kurslar katalogida nashr etilgan barcha yo‘nalishlarda o‘qish mumkin. Kurslar ro‘yxati doimiy yangilanib turadi.'),
                    __('Ro‘yxatdan qanday o‘tiladi?') => __('“Ta’limni boshlash” tugmasi orqali ro‘yxatdan o‘tish formasini to‘ldirasiz va shaxsiy kabinetingiz ochiladi.'),
                    __('AI Ustoz qanday ishlaydi?') => __('AI Ustoz dars mavzusini tushuntiradi, savollarga javob beradi, mashqlar taklif etadi va xatolaringizni tahlil qiladi.'),
                    __('Mentor bilan qanday ishlanadi?') => __('Uy vazifalaringiz mentor tomonidan tekshiriladi, izoh va baho qo‘yiladi, zarur bo‘lsa qayta ishlashga yuboriladi.'),
                    __('Kursni tugatgandan keyin qanday natija olinadi?') => __('O‘zlashtirish statistikasi shaxsiy kabinetda saqlanadi; dastur nazarda tutgan bo‘lsa, kurs yakunida sertifikat beriladi.'),
                ] as $question => $answer)
                    <details class="edu-public-faq">
                        <summary>{{ $question }}</summary>
                        <p>{{ $answer }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>
@endsection
