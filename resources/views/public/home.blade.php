@extends('layouts.public')

@section('title', 'HUQUQ AI Academy — Huquqni o‘rganing. Kelajakni yarating.')

@section('content')
    {{-- ===== HERO ===== --}}
    <section class="edu-public-hero">
        <div class="edu-public-hero-bg" aria-hidden="true"></div>
        <div class="edu-public-hero-tint" aria-hidden="true"></div>
        <div class="edu-public-container relative z-10 py-24 md:py-32 max-w-3xl">
            <p class="edu-public-eyebrow">{{ __('HUQUQ AI ACADEMY · LEGAL EDUCATION') }}</p>
            <h1 class="edu-public-hero-title">{{ __('Huquqni o‘rganing. Kelajakni yarating.') }}</h1>
            <p class="edu-public-hero-lead">
                {{ __('Zamonaviy huquqiy ta’lim, amaliy mashg’ulotlar, tajribali ustozlar va sun’iy intellekt yordamida huquqiy bilimlaringizni yangi bosqichga olib chiqing.') }}
            </p>
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('public.courses') }}" class="edu-btn-primary !px-6 !py-3">{{ __('Kurslarni ko‘rish') }}</a>
                <a href="{{ route('register') }}" class="edu-btn-hero-ghost !px-6 !py-3">{{ __('Ta’limni boshlash') }}</a>
            </div>
        </div>
    </section>

    {{-- ===== ABOUT ===== --}}
    <section id="about" class="edu-public-section">
        <div class="edu-public-container max-w-3xl text-center">
            <p class="edu-public-eyebrow">{{ __('PLATFORMA HAQIDA') }}</p>
            <h2 class="edu-public-h2">{{ __('Legal EDU nima?') }}</h2>
            <p class="edu-public-lead mt-4">
                {{ __('Legal EDU — huquqiy bilimlarni tizimli o‘rgatish, nazariyani amaliyot bilan bog‘lash va har bir o‘quvchining individual rivojlanishini kuzatishga yo‘naltirilgan zamonaviy ta’lim platformasi.') }}
            </p>
            <div class="mt-10 grid gap-4 sm:grid-cols-3">
                <div class="edu-public-mini-card">
                    <strong>{{ __('Tizimli o‘quv rejasi') }}</strong>
                    <p>{{ __('Har bir kurs modullar, darslar va topshiriqlar bilan to‘liq tuzilgan.') }}</p>
                </div>
                <div class="edu-public-mini-card">
                    <strong>{{ __('Nazariya + amaliyot') }}</strong>
                    <p>{{ __('Haqiqiy huquqiy keyslar va mashqlar orqali bilimni mustahkamlash.') }}</p>
                </div>
                <div class="edu-public-mini-card">
                    <strong>{{ __('Individual kuzatuv') }}</strong>
                    <p>{{ __('O‘quvchi progressi, baholar va tavsiyalar shaxsiy kabinetda.') }}</p>
                </div>
            </div>
        </div>
    </section>

    {{-- ===== EDUCATION DIRECTIONS / KURSLAR ===== --}}
    <section id="courses" class="edu-public-section edu-public-section-alt">
        <div class="edu-public-container">
            <div class="flex items-end justify-between gap-4 mb-8">
                <div>
                    <p class="edu-public-eyebrow">{{ __('TA’LIM YO‘NALISHLARI') }}</p>
                    <h2 class="edu-public-h2">{{ __('Kurslar') }}</h2>
                </div>
                <a href="{{ route('public.courses') }}" class="edu-public-link">{{ __('Barcha kurslar →') }}</a>
            </div>

            @if ($courses->isEmpty())
                {{-- Empty state — soxta kurs ko‘rsatmaymiz --}}
                <div class="edu-public-empty">
                    <p>{{ __('Hozircha nashr etilgan kurslar mavjud emas. Tez orada yangi kurslar qo‘shiladi.') }}</p>
                    <a href="{{ route('register') }}" class="edu-btn-primary !py-2.5 mt-4">{{ __('Ro‘yxatdan o‘tish') }}</a>
                </div>
            @else
                <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($courses as $course)
                        <article class="edu-public-course-card">
                            <div class="edu-public-course-card-body">
                                <span class="edu-public-chip">{{ $course->education_level?->value ?? __('Kurs') }}</span>
                                <h3>{{ $course->title }}</h3>
                                <p>{{ \Illuminate\Support\Str::limit(strip_tags($course->description), 120) }}</p>
                                <dl class="edu-public-course-meta">
                                    @if ($course->duration_weeks)
                                        <div><dt>{{ __('Davomiyligi') }}</dt><dd>{{ __(':n hafta', ['n' => $course->duration_weeks]) }}</dd></div>
                                    @endif
                                    @if ($course->teacher)
                                        <div><dt>{{ __('O‘qituvchi') }}</dt><dd>{{ $course->teacher->name }}</dd></div>
                                    @endif
                                </dl>
                            </div>
                            <a href="{{ route('public.courses.show', $course->slug) }}" class="edu-public-link px-5 pb-5">{{ __('Batafsil ma’lumot →') }}</a>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    {{-- ===== WHY LEGAL EDU ===== --}}
    <section id="why" class="edu-public-section">
        <div class="edu-public-container">
            <p class="edu-public-eyebrow text-center">{{ __('NEG LEGAL EDU?') }}</p>
            <h2 class="edu-public-h2 text-center">{{ __('Platforma imkoniyatlari') }}</h2>
            <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ([
                    '📚' => [__('Tizimli huquqiy ta’lim'), __('Modullar, darslar va topshiriqlardan iborat to‘liq o‘quv rejasi.')],
                    '🤖' => [__('AI Ustoz'), __('Murakkab mavzularni individual tushuntiruvchi sun’iy intellekt yordamchisi.')],
                    '👩‍🏫' => [__('Tajribali o‘qituvchilar'), __('Har bir kursni amaliyotdagi mutaxassislar olib boradi.')],
                    '✅' => [__('Mentor tekshiruvi'), __('Uy vazifalari mentor tomonidan tekshiriladi va baholanadi.')],
                    '⚖️' => [__('Amaliy keyslar'), __('Haqiqiy huquqiy vaziyatlar asosidagi kazuslar.')],
                    '📝' => [__('Test va baholash'), __('Bilimni testlar va imtihonlar orqali tekshirish.')],
                    '📈' => [__('Rivojlanish kuzatuvi'), __('O‘zlashtirish statistikasi shaxsiy kabinetda.')],
                    '🎓' => [__('Sertifikat'), __('Dastur nazarda tutgan bo‘lsa, kurs yakunida sertifikat.')],
                ] as $icon => [$title, $text])
                    <div class="edu-public-feature-card">
                        <span class="edu-public-feature-icon" aria-hidden="true">{{ $icon }}</span>
                        <h3>{{ $title }}</h3>
                        <p>{{ $text }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ===== AI USTOZ ===== --}}
    <section id="ai-ustoz" class="edu-public-section edu-public-section-alt">
        <div class="edu-public-container grid gap-10 lg:grid-cols-2 items-center">
            <div>
                <p class="edu-public-eyebrow">{{ __('AI USTOZ') }}</p>
                <h2 class="edu-public-h2">{{ __('Sun’iy intellekt — sizning shaxsiy yordamchingiz') }}</h2>
                <p class="edu-public-lead mt-4">
                    {{ __('AI Ustoz dars mavzusini tushuntiradi, savollarga javob beradi va bilimingizni tekshiradi — 24/7.') }}
                </p>
                <ul class="edu-public-checklist mt-6">
                    <li>{{ __('Murakkab huquqiy mavzularni sodda tushuntirish') }}</li>
                    <li>{{ __('Savol-javob rejimi') }}</li>
                    <li>{{ __('Mavzu bo‘yicha mashqlar') }}</li>
                    <li>{{ __('Bilimni tekshirish va xatolarni tahlil qilish') }}</li>
                    <li>{{ __('Individual tavsiyalar') }}</li>
                </ul>
            </div>
            <div class="edu-public-ai-visual" aria-hidden="true">
                <div class="edu-public-ai-bubble">{{ __('“Kazusni qanday tahlil qilaman?”') }}</div>
                <div class="edu-public-ai-bubble edu-public-ai-bubble-ai">{{ __('“Avval faktlarni, so‘ng huquqiy me’yorlarni aniqlang…”') }}</div>
                <div class="edu-public-ai-core">§ AI</div>
            </div>
        </div>
    </section>

    {{-- ===== HOW IT WORKS ===== --}}
    <section id="how-it-works" class="edu-public-section">
        <div class="edu-public-container">
            <p class="edu-public-eyebrow text-center">{{ __('QANDAY ISHLAYDI') }}</p>
            <h2 class="edu-public-h2 text-center">{{ __('Ta’lim jarayoni — 5 bosqich') }}</h2>
            <ol class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-5">
                @foreach ([
                    __('Ro‘yxatdan o‘tish'),
                    __('Kurs tanlash'),
                    __('Darslarni o‘rganish'),
                    __('AI Ustoz va mentor bilan ishlash'),
                    __('Baholash va yakunlash'),
                ] as $index => $step)
                    <li class="edu-public-step">
                        <span class="edu-public-step-number">{{ $index + 1 }}</span>
                        <h3>{{ $step }}</h3>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- ===== TEACHERS AND MENTORS ===== --}}
    @if ($teachers->isNotEmpty())
        <section id="teachers" class="edu-public-section edu-public-section-alt">
            <div class="edu-public-container">
                <p class="edu-public-eyebrow text-center">{{ __('JAMOA') }}</p>
                <h2 class="edu-public-h2 text-center">{{ __('O‘qituvchilar va mentorlar') }}</h2>
                <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($teachers as $teacher)
                        <div class="edu-public-teacher-card">
                            <span class="edu-brand-mark !h-12 !w-12 !text-xl" aria-hidden="true">{{ mb_substr($teacher->name, 0, 1) }}</span>
                            <h3>{{ $teacher->name }}</h3>
                            <p>{{ $teacher->role->value === 'teacher' ? __('O‘qituvchi') : __('Mentor') }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- ===== STUDENT EXPERIENCE ===== --}}
    <section id="experience" class="edu-public-section">
        <div class="edu-public-container">
            <p class="edu-public-eyebrow text-center">{{ __('O‘QUVCHI TAJRIBASI') }}</p>
            <h2 class="edu-public-h2 text-center">{{ __('Platformada nimalar mavjud') }}</h2>
            <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ([
                    '🏠' => __('Shaxsiy kabinet'),
                    '📖' => __('Darslar va materiallar'),
                    '🗒️' => __('Uy vazifalari'),
                    '🤖' => __('AI Ustoz'),
                    '✅' => __('Mentor tekshiruvi'),
                    '📝' => __('Testlar'),
                    '📊' => __('O‘zlashtirish statistikasi'),
                ] as $icon => $label)
                    <div class="edu-public-mini-card !flex-row items-center gap-3 !text-left">
                        <span class="text-2xl" aria-hidden="true">{{ $icon }}</span>
                        <strong>{{ $label }}</strong>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ===== FAQ (accordion) ===== --}}
    <section id="faq" class="edu-public-section edu-public-section-alt">
        <div class="edu-public-container max-w-3xl">
            <p class="edu-public-eyebrow text-center">{{ __('SAVOL-JAVOB') }}</p>
            <h2 class="edu-public-h2 text-center">{{ __('Ko‘p so‘raladigan savollar') }}</h2>
            <div class="mt-8 space-y-3">
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

    {{-- ===== FINAL CTA ===== --}}
    <section class="edu-public-final-cta">
        <div class="edu-public-container text-center py-16">
            <h2 class="edu-public-h2 !text-white">{{ __('Huquqiy bilimlaringizni bugundan rivojlantiring.') }}</h2>
            <div class="mt-8 flex flex-wrap justify-center gap-3">
                <a href="{{ route('register') }}" class="edu-btn-primary !px-6 !py-3">{{ __('Ta’limni boshlash') }}</a>
                <a href="{{ route('login') }}" class="edu-btn-hero-ghost !px-6 !py-3">{{ __('Tizimga kirish') }}</a>
            </div>
        </div>
    </section>
@endsection
