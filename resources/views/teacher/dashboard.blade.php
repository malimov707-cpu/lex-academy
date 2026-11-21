@extends('layouts.app')

@section('title', __('O‘qituvchi paneli'))

@php
    $slides = [
        [__('Innovatsion ta’lim'), __('Legal EDU bilan'), __('huquqiy bilim yangi bosqichda'), __('3D sud jarayoni, AI ustoz va interaktiv ta’lim'), __('Yangi simulyatsiya yaratish'), route('admin.courtroom.create')],
        [__('Mening kurslarim'), __('Huquqiy ta’lim'), __('o‘z kursingizdan boshlanadi'), __('Kurslaringizga yangi modullar, darslar va topshiriqlar qo‘shing.'), __('Kurslarni boshqarish'), route('admin.courses.index')],
        [__('Huquqiy kutubxona'), __('Dars uchun'), __('ishonchli huquqiy manbalar'), __('Kodekslar, qonunlar va darsliklardan foydalaning.'), __('Kutubxonani ochish'), route('library.index')],
        [__('Dars jadvali'), __('Navbatdagi darsni'), __('bugun rejalashtiring'), __('Mashg‘ulotlar jadvali va talabalar davomatini kuzating.'), __('Jadvalni ochish'), route('schedule.index')],
    ];
    $scoreChange = $metricDetails['score_change'];
    $scoreHint = $scoreChange === null
        ? __('Yakuniy test natijalari')
        : __(':n foiz punkt · 30 kun', ['n' => ($scoreChange > 0 ? '+' : '').round($scoreChange, 1)]);
    $metrics = [
        ['blue', 'learning', __('Mening kurslarim'), $stats['courses'], __('ta'), __(':n ta yangi · 30 kun', ['n' => $metricDetails['new_courses']])],
        ['green', 'users', __('Jami talabalar'), $stats['active_students'], __('nafar'), __(':n ta yangi · 30 kun', ['n' => $metricDetails['new_students']])],
        ['violet', 'bars', __('O‘rtacha natija'), $stats['average_score'] === null ? '—' : round($stats['average_score'], 1).'%', null, $scoreHint],
        ['gold', 'star', __('Eng yaxshi talaba'), $stats['best_score'] === null ? '—' : round($stats['best_score'], 1).'%', null, $metricDetails['best_student'] ?? __('Hali natija yo‘q')],
    ];
    $series = ['courtroom' => __('Virtual Courtroom'), 'courses' => __('Kurslar'), 'quizzes' => __('Testlar')];
    $months = collect(range(1, 12))->map(fn (int $month) => now()->startOfYear()->month($month)->shortMonthName);
    $activityLabels = [
        'courtroom' => __('Sud simulyatsiyasi'),
        'enrollment' => __('Kursga yozildi'),
        'quiz' => __('Test topshirdi'),
        'submission' => __('Ish topshirdi'),
    ];
    $courseImages = ['images/justice-court.jpg', 'images/teacher-courtroom-hero.webp', 'images/control-center-hero.jpg'];
@endphp

@section('content')
<div class="teacher-overview" data-teacher-dashboard>
    <section class="teacher-feature" aria-label="{{ __('O‘qituvchi imkoniyatlari') }}" data-teacher-slides>
        <div class="teacher-feature-copy" aria-live="polite">
            @foreach ($slides as [$badge, $title, $subtitle, $description, $action, $url])
                <div data-teacher-slide @if (! $loop->first) hidden @endif>
                    <span class="teacher-feature-tag">{{ $badge }}</span>
                    @if ($loop->first)
                        <h1>{{ $title }}<br>{{ $subtitle }}</h1>
                    @else
                        <h2>{{ $title }}<br>{{ $subtitle }}</h2>
                    @endif
                    <p>{{ $description }}</p>
                    <a class="teacher-feature-action" href="{{ $url }}">{{ $action }} <span aria-hidden="true">→</span></a>
                </div>
            @endforeach
        </div>
        <div class="teacher-feature-dots" role="group" aria-label="{{ __('Slaydlar') }}">
            @foreach ($slides as $slide)
                <button type="button" data-teacher-slide-button aria-label="{{ $slide[0] }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}"><span></span></button>
            @endforeach
        </div>
    </section>

    <section class="teacher-metrics" aria-label="{{ __('Asosiy ko‘rsatkichlar') }}">
        @foreach ($metrics as [$color, $icon, $label, $value, $unit, $hint])
            <article class="teacher-metric teacher-metric-{{ $color }}">
                <span class="teacher-metric-icon">
                    @if ($icon === 'star')
                        <svg viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m12 2.6 2.85 5.95 6.55.88-4.78 4.55 1.14 6.5L12 17.33l-5.76 3.15 1.14-6.5-4.78-4.55 6.55-.88z"/></svg>
                    @elseif ($icon === 'bars')
                        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><rect x="3" y="13" width="4.5" height="8" rx="1.2"/><rect x="9.75" y="8" width="4.5" height="13" rx="1.2"/><rect x="16.5" y="3" width="4.5" height="18" rx="1.2"/></svg>
                    @else
                        <x-nav-icon :name="$icon" />
                    @endif
                </span>
                <div class="teacher-metric-copy">
                    <h2>{{ $label }}</h2>
                    <strong>{{ $value }}@if ($unit) <span class="teacher-metric-unit">{{ $unit }}</span>@endif</strong>
                    <small>{{ $hint }}</small>
                </div>
            </article>
        @endforeach
    </section>

    <div class="teacher-overview-grid">
        <section class="teacher-panel teacher-statistics" id="teacher-statistics" data-teacher-chart
                 data-days="{{ json_encode($activityDays) }}" data-months="{{ $months->toJson() }}">
            <header class="teacher-panel-heading">
                <h2 id="teacher-chart-title"><x-nav-icon name="analytics" />{{ __('O‘quv jarayoni statistikasi') }}</h2>
                <select aria-label="{{ __('Statistika davri') }}" data-teacher-period>
                    @foreach ([7, 30, 90] as $days)
                        <option value="{{ $days }}" @selected($days === 30)>{{ __('Oxirgi :n kun', ['n' => $days]) }}</option>
                    @endforeach
                </select>
            </header>
            <div class="teacher-chart-legend">
                @foreach ($series as $key => $label)
                    <span class="teacher-series-{{ $key }}" data-series="{{ $key }}"><i aria-hidden="true"></i>{{ $label }}</span>
                @endforeach
            </div>
            <div class="teacher-chart-area">
                <svg class="teacher-chart-svg" aria-hidden="true" focusable="false">
                    <defs>
                        @foreach ($series as $key => $label)
                            <linearGradient id="teacher-fill-{{ $key }}" class="teacher-series-{{ $key }}" x1="0" x2="0" y1="0" y2="1">
                                <stop offset="0%" stop-color="currentColor" stop-opacity=".16"/>
                                <stop offset="100%" stop-color="currentColor" stop-opacity=".015"/>
                            </linearGradient>
                        @endforeach
                    </defs>
                    <g data-plot-grid></g>
                    <g data-plot-lines></g>
                    <g data-plot-cursor></g>
                </svg>
                <input type="range" min="0" max="29" value="29" data-teacher-point aria-label="{{ __('Kun bo‘yicha faollik') }}">
                <div class="teacher-chart-tooltip" data-teacher-tooltip hidden>
                    <strong></strong>
                    @foreach ($series as $key => $label)
                        <div class="teacher-series-{{ $key }}"><i aria-hidden="true"></i>{{ $label }} <b data-point-value="{{ $key }}"></b></div>
                    @endforeach
                </div>
                <p class="teacher-chart-empty" data-chart-empty hidden>{{ __('Bu davrda faollik qayd etilmagan.') }}</p>
            </div>
            <noscript>{{ __('Oxirgi 30 kun') }} · @foreach ($series as $key => $label) {{ $label }} {{ collect($activityDays)->take(-30)->sum($key) }} @endforeach</noscript>
        </section>

        <section class="teacher-panel teacher-feed" id="teacher-activity">
            <header class="teacher-panel-heading">
                <h2><x-nav-icon name="clock" />{{ __('So‘nggi faollik') }}</h2>
                @if ($recentActivity->isNotEmpty())
                    <button class="teacher-text-action" type="button" data-teacher-activity-open aria-haspopup="dialog">{{ __('Barchasi') }} <span aria-hidden="true">→</span></button>
                @endif
            </header>
            @include('teacher.activity-list', ['items' => $recentActivity->take(4)])
            @if ($recentActivity->isNotEmpty())
                <dialog class="teacher-activity-dialog" aria-labelledby="teacher-activity-title" data-teacher-activity-dialog>
                    <header class="teacher-panel-heading">
                        <div>
                            <h2 id="teacher-activity-title">{{ __('So‘nggi faollik') }}</h2>
                            <p>{{ __('So‘nggi :n ta voqea', ['n' => $recentActivity->count()]) }}</p>
                        </div>
                        <form method="dialog"><button class="teacher-dialog-close" aria-label="{{ __('Yopish') }}"><span aria-hidden="true">×</span></button></form>
                    </header>
                    @include('teacher.activity-list', ['items' => $recentActivity])
                </dialog>
            @endif
        </section>

        <section class="teacher-panel teacher-course-panel" id="teacher-courses">
            <header class="teacher-panel-heading">
                <h2><x-nav-icon name="open-book" />{{ __('Mening kurslarim') }}</h2>
                <a class="teacher-text-action" href="{{ route('admin.courses.index') }}">{{ __('Barchasi') }} <span aria-hidden="true">→</span></a>
            </header>
            <div class="teacher-course-cards">
                @forelse ($courses->take(3) as $course)
                    <a class="teacher-course-card" href="{{ route('admin.courses.modules.index', $course) }}">
                        <img src="{{ $course->cover_image ? route('courses.cover', $course) : asset($courseImages[$loop->index]) }}" alt="" loading="lazy">
                        <div>
                            <h3>{{ $course->title }}</h3>
                            <small>{{ __(':n dars', ['n' => $course->lessons_count]) }}</small>
                            @if ($course->progress_percent === null)
                                <span>{{ __($course->status) }}</span>
                            @else
                                <span>{{ __(':n% o‘zlashtirish', ['n' => $course->progress_percent]) }}</span>
                                <progress class="teacher-course-progress" value="{{ $course->progress_percent }}" max="100" aria-hidden="true"></progress>
                            @endif
                        </div>
                        <span class="teacher-play" aria-hidden="true">▶</span>
                    </a>
                @empty
                    <div class="teacher-empty"><x-nav-icon name="courses" /><strong>{{ __('Hali kurs yaratilmagan') }}</strong><a href="{{ route('admin.courses.index') }}#course-title">{{ __('Kurs yaratish') }} →</a></div>
                @endforelse
            </div>
        </section>

        <a id="teacher-courtroom" class="teacher-court-promo" href="{{ route('admin.courtroom.index') }}">
            <div><h2><x-nav-icon name="courtroom" />{{ __('Virtual Courtroom') }}</h2>
                <p>{{ __('3D sud zalida amaliy mashg‘ulotlar') }}</p>
                <span class="teacher-court-link">{{ __('Kirish') }} <span aria-hidden="true">→</span></span>
            </div>
            <span class="teacher-court-play" aria-hidden="true">▶</span>
        </a>

        <section class="teacher-panel teacher-details" id="teacher-lessons">
            <header class="teacher-panel-heading"><h2>{{ __('Kurslar holati') }}</h2><a class="teacher-text-action" href="{{ route('admin.courses.index') }}">{{ __('Kurslarni boshqarish') }} <span aria-hidden="true">→</span></a></header>
            <div class="teacher-course-totals">
                @foreach ([__('Darslar') => $lessonsCount, __('Nashr etilgan') => $stats['published'], __('Qoralama kurslar') => $drafts, __('Kursga yozilishlar') => $stats['students']] as $label => $total)
                    <div><strong>{{ $total }}</strong><small>{{ $label }}</small></div>
                @endforeach
            </div>
            @if ($courses->isNotEmpty())
                <ul class="teacher-detail-list">
                    @foreach ($courses as $course)
                        <li><a href="{{ route('admin.courses.modules.index', $course) }}">
                            <div><strong>{{ $course->title }}</strong><small>{{ __(':s talaba · :a topshiriq', ['s' => $course->enrollments_count, 'a' => $course->assignments_count]) }}</small></div>
                            <span>{{ __($course->status) }}</span>
                        </a></li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="teacher-panel teacher-details" id="teacher-submissions">
            <header class="teacher-panel-heading"><h2>{{ __('So‘nggi ishlar') }}</h2><span class="teacher-queue-total">{{ __('Tekshirilishi kerak') }} · {{ $stats['pending'] }}</span></header>
            <ul class="teacher-detail-list">
                @forelse ($recentSubmissions as $submission)
                    <li><a href="{{ route('admin.courses.assignments.index', $submission->assignment->course_id) }}">
                        <div><strong>{{ $submission->assignment->title }}</strong><small>{{ $submission->student->name }} · {{ $submission->submitted_at?->diffForHumans() ?? __($submission->status) }}</small></div>
                        <span aria-hidden="true">→</span>
                    </a></li>
                @empty
                    <li class="teacher-empty"><x-nav-icon name="assignments" /><strong>{{ __('Hozircha ish yuborilmagan.') }}</strong></li>
                @endforelse
            </ul>
            @if ($courses->isNotEmpty())
                <details class="teacher-grading-links">
                    <summary>{{ __('Testlarni baholash') }}</summary>
                    <ul class="teacher-detail-list">
                        @foreach ($courses->where('quizzes_count', '>', 0) as $course)
                            <li><a href="{{ route('admin.courses.quizzes.index', $course) }}">{{ $course->title }} <span aria-hidden="true">→</span></a></li>
                        @endforeach
                        <li><a class="teacher-text-action" href="{{ route('admin.courses.index') }}">{{ __('Barcha kurslar') }} <span aria-hidden="true">→</span></a></li>
                    </ul>
                </details>
            @endif
        </section>
    </div>
</div>
@endsection
