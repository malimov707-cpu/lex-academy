@extends('layouts.app')

@section('title', __('Boshqaruv'))

@php use App\Enums\UserRole; @endphp

@section('content')
<div class="staff-shell">
    @php
        $roleLabel = __($user->role->value);
        $hour = (int) now()->format('G');
        $greeting = $hour < 12 ? __('Xayrli tong') : ($hour < 18 ? __('Xayrli kun') : __('Xayrli oqshom'));

        $subtitle = match (true) {
            $user->isAdmin() => __('Ta’lim markazining bugungi holati — real ma’lumotlar bilan.'),
            $user->hasRole(UserRole::Teacher) => __('Kurslaringiz, talabalaringiz va tekshirish navbati bir joyda.'),
            $user->hasRole(UserRole::Mentor) => __('Biriktirilgan talabalaringiz va tekshirish uchun ishlar.'),
            default => __('Bilimingizni bosqichma-bosqich mustahkamlang.'),
        };

        $ctaRoute = match (true) {
            $user->isAdmin() => route('admin.courses.index'),
            $user->hasRole(UserRole::Teacher) => route('admin.courses.index'),
            $user->hasRole(UserRole::Mentor) => route('mentor.reviews'),
            default => route('student.dashboard'),
        };
        $ctaLabel = $user->hasRole(UserRole::Student) ? __('O‘qishni davom ettirish') : __('Ish maydoniga o‘tish');

        $icons = [
            'courses' => 'courses', 'published_courses' => 'certificate', 'my_courses' => 'learning',
            'students' => 'users', 'assigned_students' => 'users', 'teachers' => 'lesson', 'mentors' => 'review',
            'pending_reviews' => 'review', 'completed_lessons' => 'assignments',
            'learning_progress' => 'analytics', 'upcoming_deadlines' => 'schedule',
        ];
        $labels = [
            'students' => __('Talabalar'), 'teachers' => __('O‘qituvchilar'), 'mentors' => __('Mentorlar'),
            'courses' => __('Kurslar'), 'published_courses' => __('Nashr etilgan kurslar'),
            'pending_reviews' => __('Tekshirilishi kerak'), 'my_courses' => __('Mening kurslarim'),
            'assigned_students' => __('Biriktirilgan talabalar'),
            'completed_lessons' => __('Tugallangan darslar'),
            'learning_progress' => __('O‘zlashtirish'), 'upcoming_deadlines' => __('Yaqin deadline'),
        ];

        $nextSteps = match (true) {
            $user->isAdmin() => [
                ['admin.courses.index', __('Kurslarni boshqarish'), 'courses'],
                ['admin.users.index', __('Foydalanuvchilar va rollar'), 'users'],
                ['admin.reports.index', __('Hisobotlar va audit'), 'reports'],
                ['admin.settings.edit', __('Akademiya sozlamalari'), 'settings'],
            ],
            $user->hasRole(UserRole::Teacher) => [
                ['admin.courses.index', __('Mening kurslarim'), 'courses'],
                ['mentor.reviews', __('Tekshirish maydoni'), 'review'],
                ['schedule.index', __('Dars jadvali'), 'schedule'],
            ],
            $user->hasRole(UserRole::Mentor) => [
                ['mentor.reviews', __('Tekshirish navbati'), 'review'],
                ['mentor.students.index', __('Biriktirilgan talabalar'), 'users'],
                ['conversations.index', __('Shaxsiy muloqot'), 'chat'],
            ],
            default => [
                ['student.dashboard', __('Mening kurslarim'), 'learning'],
                ['student.assignments', __('Topshiriqlar'), 'assignments'],
                ['student.cases.index', __('Huquqiy kazuslar'), 'scale'],
                ['certificates.index', __('Sertifikatlarim'), 'certificate'],
            ],
        };
    @endphp

    {{-- ===== HERO ===== --}}
    <section class="staff-hero">
        <div class="staff-hero-copy">
            <span class="staff-hero-badge"><i aria-hidden="true"></i>{{ $roleLabel }} · HUQUQ AI Academy</span>
            <h1>{{ $greeting }}, {{ $user->name }}</h1>
            <p>{{ $subtitle }}</p>
            <a href="{{ $ctaRoute }}" class="staff-hero-cta">{{ $ctaLabel }}<span aria-hidden="true">↗</span></a>
        </div>
        <span class="staff-hero-mark" aria-hidden="true">§</span>
    </section>

    {{-- ===== KPI KARTALAR ===== --}}
    @php $kpiCount = count($stats); @endphp
    <section class="staff-kpi-grid" aria-label="{{ __('Asosiy ko‘rsatkichlar') }}">
        @foreach ($stats as $label => $value)
            <article class="staff-kpi">
                <span class="staff-kpi-icon"><x-nav-icon :name="$icons[$label] ?? 'analytics'" /></span>
                <div>
                    <small>{{ $labels[$label] ?? __($label) }}</small>
                    <strong>{{ is_int($value) && $label === 'learning_progress' ? $value.'%' : $value }}</strong>
                </div>
            </article>
        @endforeach
    </section>

    {{-- ===== KEYINGI QADAMLAR ===== --}}
    <div class="staff-grid">
        <section class="staff-panel">
            <div class="staff-panel-head">
                <h2>{{ __('Keyingi qadamlar') }}</h2>
                <span>{{ __('Rolingiz bo‘yicha tavsiya etilgan bo‘limlar') }}</span>
            </div>
            <ul class="staff-stack">
                @foreach ($nextSteps as [$routeName, $label, $icon])
                    <li>
                        <a href="{{ route($routeName) }}" class="staff-row">
                            <span class="staff-kpi-icon" style="width:38px;height:38px;border-radius:11px"><x-nav-icon :name="$icon" style="width:18px;height:18px" /></span>
                            <span class="staff-row-main"><strong>{{ $label }}</strong></span>
                            <span class="staff-row-action" aria-hidden="true">→</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>

        <aside class="staff-stack">
            <section class="staff-panel" aria-label="{{ __('AI Ustoz') }}">
                <div class="staff-panel-head"><h2>{{ __('AI Ustoz') }}</h2></div>
                <p class="text-sm" style="color:var(--edu-text-muted)">{{ __('Dars kontekstida tushuntirish, mashq va kazus tahlili.') }}</p>
                <div class="staff-ai-note" style="margin-top:14px">
                    <p class="!mt-0 text-xs font-semibold" style="color:#3c5170">{{ __('Pedagogik model') }}</p>
                    <p class="!mt-1">ASSESS → EXPLAIN → ASK → PRACTICE → EVALUATE → FEEDBACK → REPEAT</p>
                </div>
                <p class="mt-4 text-xs leading-relaxed" style="color:var(--edu-text-muted)">
                    {{ __('AI Ustoz dars sahifasidan ochiladi — u kurs va dars kontekstini biladi.') }}
                </p>
            </section>
        </aside>
    </div>
</div>
@endsection
