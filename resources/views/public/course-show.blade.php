@extends('layouts.public')

@section('title', $course->title . ' — HUQUQ AI Academy')
@section('meta_description', \Illuminate\Support\Str::limit(strip_tags($course->description), 150))

@section('content')
    <section class="edu-public-hero !min-h-[35vh]">
        <div class="edu-public-hero-bg" aria-hidden="true"></div>
        <div class="edu-public-hero-tint" aria-hidden="true"></div>
        <div class="edu-public-container relative z-10 py-16">
            <a href="{{ route('public.courses') }}" class="edu-public-link !text-white/80">{{ __('← Kurslar') }}</a>
            <h1 class="edu-public-hero-title !text-4xl mt-3">{{ $course->title }}</h1>
        </div>
    </section>

    <section class="edu-public-section">
        <div class="edu-public-container max-w-3xl">
            <div class="edu-public-mini-card !text-left">
                <p>{{ nl2br(e(strip_tags($course->description))) }}</p>
                <dl class="edu-public-course-meta mt-6">
                    @if ($course->duration_weeks)
                        <div><dt>{{ __('Davomiyligi') }}</dt><dd>{{ __(':n hafta', ['n' => $course->duration_weeks]) }}</dd></div>
                    @endif
                    @if ($course->teacher)
                        <div><dt>{{ __('O‘qituvchi') }}</dt><dd>{{ $course->teacher->name }}</dd></div>
                    @endif
                    <div><dt>{{ __('Holati') }}</dt><dd>{{ __('Nashr etilgan') }}</dd></div>
                </dl>
                <a href="{{ route('register') }}" class="edu-btn-primary !py-2.5 mt-6 inline-flex">{{ __('Kursga yozilish') }}</a>
            </div>
        </div>
    </section>
@endsection
