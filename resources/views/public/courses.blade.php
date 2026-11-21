@extends('layouts.public')

@section('title', __('Kurslar') . ' — HUQUQ AI Academy')
@section('meta_description', __('Nashr etilgan huquqiy kurslar katalogi.'))

@section('content')
    <section class="edu-public-hero !min-h-[40vh]">
        <div class="edu-public-hero-bg" aria-hidden="true"></div>
        <div class="edu-public-hero-tint" aria-hidden="true"></div>
        <div class="edu-public-container relative z-10 py-20">
            <h1 class="edu-public-hero-title !text-4xl">{{ __('Kurslar katalogi') }}</h1>
            <p class="edu-public-hero-lead">{{ __('Platformada nashr etilgan barcha ta’lim yo‘nalishlari.') }}</p>
        </div>
    </section>

    <section class="edu-public-section">
        <div class="edu-public-container">
            @if ($courses->isEmpty())
                <div class="edu-public-empty">
                    <p>{{ __('Hozircha nashr etilgan kurslar mavjud emas. Tez orada yangi kurslar qo‘shiladi.') }}</p>
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
                <div class="mt-8">{{ $courses->links() }}</div>
            @endif
        </div>
    </section>
@endsection
