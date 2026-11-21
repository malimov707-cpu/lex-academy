<!DOCTYPE html>
<html lang="{{ app()->getLocale() === 'uz-cyr' ? 'uz-Cyrl' : (app()->getLocale() === 'ru' ? 'ru' : 'uz-Latn') }}" data-theme="{{ request()->cookie('academy_theme') === 'dark' ? 'dark' : 'light' }}" class="h-full {{ request()->cookie('academy_theme') === 'dark' ? 'dark' : '' }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('O‘quvchi paneli')) · HUQUQ AI Academy</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="student-dashboard-page">
<a href="#main-content" class="edu-skip-link">{{ __('Asosiy tarkibga o‘tish') }}</a>
<div @class(['student-shell', 'student-shell-has-profile' => \Illuminate\Support\Facades\View::hasSection('profile')])>
    <button class="student-menu-scrim" type="button" data-student-menu-close hidden aria-label="{{ __('Menyuni yopish') }}"></button>
    <aside id="student-navigation" class="student-sidebar" data-student-navigation>
        <a href="{{ route('student.dashboard') }}" class="student-brand"><span aria-hidden="true">§</span><strong>{{ $academySettings?->name ?? 'HUQUQ AI Academy' }}<small>{{ __('Ta’lim maydoni') }}</small></strong></a>
        <div class="student-sidebar-content">
            <p class="student-nav-caption">{{ __('Asosiy bo‘limlar') }}</p>
            <nav class="student-nav" aria-label="{{ __('Akademiya bo‘limlari') }}">
                @foreach ([
                    ['student.dashboard', 'O‘quvchi paneli', 'dashboard', ['student.dashboard']],
                    ['conversations.index', 'Xabarlar', 'chat', ['conversations.*']],
                    ['library.index', 'Huquqiy kutubxona', 'sources', ['library.*']],
                    ['legal-information-check.index', 'Da’voni tekshirish', 'law', ['legal-information-check.*']],
                    ['ai.index', 'AI Ustoz', 'tutor', ['ai.index', 'student.tutor*']],
                    ['student.courtroom.index', 'Virtual sud', 'courtroom', ['student.courtroom.*']],
                    ['courtroom.groups.index', 'Guruhli Virtual sud', 'groups', ['courtroom.groups.*', 'courtroom.results.*']],
                    ['student.assignments', 'Topshiriqlar', 'assignments', ['student.assignments*']],
                    ['student.cases.index', 'Kazuslar', 'cases', ['student.cases.*']],
                    ['student.document-trainer.index', 'Hujjat trenajyori', 'law', ['student.document-trainer.*']],
                    ['schedule.index', 'Jadval', 'schedule', ['schedule.*']],
                    ['announcements.index', 'E’lonlar', 'announcement', ['announcements.*']],
                    ['notifications.index', 'Bildirishnomalar', 'notification', ['notifications.*']],
                    ['certificates.index', 'Sertifikatlar', 'certificate', ['certificates.*']],
                ] as [$routeName, $label, $icon, $patterns])
                    @php($isActive = request()->routeIs(...$patterns))
                    <a href="{{ route($routeName) }}" class="{{ $isActive ? 'is-active' : '' }} {{ $routeName === 'ai.index' ? 'student-nav-ai' : '' }}" @if($isActive) aria-current="page" @endif><x-nav-icon :name="$icon" /><span>{{ __($label) }}</span>@if($routeName === 'ai.index')<small>AI</small>@endif</a>
                @endforeach
                @php($isLearning = request()->routeIs('student.course', 'student.lesson', 'student.quiz.*', 'student.analytics'))
                <a href="{{ route('student.dashboard') }}#my-courses" class="{{ $isLearning ? 'is-active' : '' }}" @if($isLearning) aria-current="page" @endif><x-nav-icon name="courses" /><span>{{ __('Darslarim') }}</span></a>
            </nav>
            <div class="student-sidebar-mentors"><p class="student-nav-caption">{{ __('Mentorlarim') }}</p>
                @forelse ($studentNavigationMentors as $assignment)<a href="{{ route('conversations.show', $assignment) }}" class="student-person"><span class="student-person-avatar">{{ mb_strtoupper(mb_substr($assignment->mentor->name, 0, 1)) }}</span><span><strong>{{ $assignment->mentor->name }}</strong><small>{{ __('Mentor') }}</small></span></a>@empty<p class="student-muted-note">{{ __('Mentor hali biriktirilmagan.') }}</p>@endforelse
            </div>
        </div>
        <div class="student-sidebar-bottom">
            <p class="student-nav-caption">{{ __('Sozlamalar') }}</p>
            <details class="student-preferences"><summary><x-nav-icon name="settings" /><span>{{ __('Sozlamalar') }}</span></summary><div>
                <form method="POST" action="{{ route('locale.update') }}">@csrf<label for="student-locale">{{ __('Interfeys tili') }}</label><select id="student-locale" name="locale">@foreach (['uz-latin' => 'O‘zbekcha', 'uz-cyr' => 'Ўзбекча', 'ru' => 'Русский'] as $key => $label)<option value="{{ $key }}" @selected(app()->getLocale() === $key)>{{ $label }}</option>@endforeach</select><button>{{ __('Tilni tanlash') }}</button></form>
                <form method="POST" action="{{ route('theme.update') }}">@csrf<input type="hidden" name="theme" value="{{ request()->cookie('academy_theme') === 'dark' ? 'light' : 'dark' }}"><button>{{ request()->cookie('academy_theme') === 'dark' ? __('Kunduzgi mavzu') : __('Tungi mavzu') }}</button></form>
                <form method="POST" action="{{ route('bandwidth.update') }}">@csrf<button>{{ request()->cookie('academy_bandwidth') === 'low' ? __('Standart rejim') : __('Tejamkor rejim') }}</button></form>
            </div></details>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="student-logout"><x-nav-icon name="logout" /><span>{{ __('Tizimdan chiqish') }}</span></button></form>
        </div>
    </aside>
    <main id="main-content" class="student-main">
        <div class="student-search-row">
            <button type="button" class="student-menu-toggle" data-student-menu-open aria-expanded="false" aria-controls="student-navigation" aria-label="{{ __('Menyuni ochish') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg></button>
            <form method="GET" action="{{ route('search') }}" role="search" class="student-search"><x-nav-icon name="search" /><label for="student-search" class="sr-only">{{ __('Kursni qidirish') }}</label><input id="student-search" type="search" name="q" minlength="2" placeholder="{{ __('Kurs yoki darsni qidiring…') }}"><button type="submit" class="sr-only">{{ __('Qidirish') }}</button></form>
            @if(request()->routeIs('student.dashboard'))
                <button type="button" class="student-filter" data-student-filter aria-pressed="false" aria-label="{{ __('Faqat tugallanmagan kurslar') }}" title="{{ __('Faqat tugallanmagan kurslar') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M4 4h16l-6 7v7l-4 2v-9z"/></svg></button>
            @endif
        </div>
        @include('layouts.partials.feedback')
        @yield('content')
    </main>
    @hasSection('profile')
        <aside class="student-profile-rail" aria-label="{{ __('Mening profilim') }}">@yield('profile')</aside>
    @endif
</div>
@stack('scripts')
</body>
</html>
