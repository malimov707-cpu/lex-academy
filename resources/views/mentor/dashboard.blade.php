@extends('layouts.app')

@section('title', __('Mentor paneli'))

@section('content')
<div class="staff-shell">
    {{-- ===== HERO ===== --}}
    <section class="staff-hero">
        <div class="staff-hero-copy">
            <span class="staff-hero-badge"><i aria-hidden="true"></i>{{ __('Mentor') }} · HUQUQ AI Academy</span>
            <h1>{{ __('Xayrli kun,') }} {{ auth()->user()->name }}</h1>
            <p>{{ __('Biriktirilgan talabalaringiz, tekshirish navbati va so‘nggi baholaringiz.') }}</p>
            <a href="{{ route('mentor.reviews') }}" class="staff-hero-cta">{{ __('Tekshirish navbati') }}<span aria-hidden="true">↗</span></a>
        </div>
        <span class="staff-hero-mark" aria-hidden="true">§</span>
    </section>

    {{-- ===== KPI ===== --}}
    <section class="staff-kpi-grid" aria-label="{{ __('Ko‘rsatkichlar') }}">
        @include('partials.kpi-card', ['icon' => 'users', 'value' => $stats['students'], 'label' => __('Biriktirilgan talabalar')])
        @include('partials.kpi-card', ['icon' => 'review', 'value' => $stats['pending'], 'label' => __('Tekshirilishi kerak'), 'hint' => __('Navbatda turgan ishlar')])
        @include('partials.kpi-card', ['icon' => 'assignments', 'value' => $stats['reviewed_week'], 'label' => __('Shu hafta baholangan')])
        @include('partials.kpi-card', ['icon' => 'chat', 'value' => $stats['revision'], 'label' => __('Qayta ishlashga yuborilgan')])
    </section>

    <div class="staff-grid">
        {{-- ===== Tekshirish navbati ===== --}}
        <section class="staff-panel" aria-label="{{ __('Tekshirish navbati') }}">
            <div class="staff-panel-head">
                <h2>{{ __('Tekshirish navbati') }}</h2>
                <a href="{{ route('mentor.reviews') }}">{{ __('Barchasi') }} →</a>
            </div>

            <ul class="staff-stack">
                @forelse ($queue as $submission)
                    <li>
                        <a href="{{ route('mentor.reviews.show', $submission) }}" class="staff-row">
                            <span class="staff-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($submission->student->name, 0, 1)) }}</span>
                            <span class="staff-row-main">
                                <strong>{{ $submission->assignment->title }}</strong>
                                <small>{{ $submission->student->name }} · {{ $submission->submitted_at?->diffForHumans() }}</small>
                            </span>
                            <span class="edu-badge edu-badge-warning staff-row-action">{{ __('Kutilmoqda') }}</span>
                        </a>
                    </li>
                @empty
                    <li class="staff-empty">
                        <x-nav-icon name="review" class="h-6 w-6 opacity-50" />
                        <strong>{{ __('Navbat bo‘sh') }}</strong>
                        <span class="block mt-1 text-sm">{{ __('Barcha ishlar tekshirilgan.') }}</span>
                    </li>
                @endforelse
            </ul>
        </section>

        <aside class="staff-stack">
            {{-- ===== Talabalarim ===== --}}
            <section class="staff-panel" aria-label="{{ __('Talabalarim') }}">
                <div class="staff-panel-head">
                    <h2>{{ __('Talabalarim') }}</h2>
                    <a href="{{ route('mentor.students.index') }}">{{ __('Barchasi') }} →</a>
                </div>
                <ul class="staff-person-list">
                    @forelse ($students as $student)
                        <li>
                            <span class="staff-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($student->name, 0, 1)) }}</span>
                            <span class="truncate">{{ $student->name }}</span>
                        </li>
                    @empty
                        <li class="text-sm" style="color:var(--edu-text-muted)">{{ __('Hali talaba biriktirilmagan.') }}</li>
                    @endforelse
                </ul>
            </section>

            {{-- ===== So'nggi baholar ===== --}}
            <section class="staff-panel" aria-label="{{ __('So‘nggi baholar') }}">
                <div class="staff-panel-head"><h2>{{ __('So‘nggi baholar') }}</h2></div>
                <ul class="staff-stack">
                    @forelse ($recent as $review)
                        <li class="staff-row !py-3">
                            <span class="staff-avatar" style="width:31px;height:31px;font-size:11px" aria-hidden="true">{{ mb_strtoupper(mb_substr($review->submission->student->name, 0, 1)) }}</span>
                            <span class="staff-row-main">
                                <strong>{{ $review->submission->student->name }}</strong>
                                <small>{{ $review->created_at?->diffForHumans() }}</small>
                            </span>
                            <span class="staff-score-pill {{ $review->decision === 'approved' ? 'edu-badge-success' : ($review->decision === 'rejected' ? 'edu-badge-danger' : 'edu-badge-warning') }}">
                                {{ $review->score }}
                            </span>
                        </li>
                    @empty
                        <li class="text-sm" style="color:var(--edu-text-muted)">{{ __('Hali baho berilmagan.') }}</li>
                    @endforelse
                </ul>
            </section>
        </aside>
    </div>
</div>
@endsection
