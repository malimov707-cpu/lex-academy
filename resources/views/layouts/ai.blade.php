<!DOCTYPE html>
<html lang="{{ app()->getLocale() === 'uz-cyr' ? 'uz-Cyrl' : (app()->getLocale() === 'ru' ? 'ru' : 'uz-Latn') }}" data-theme="{{ request()->cookie('academy_theme') === 'dark' ? 'dark' : 'light' }}" class="h-full {{ request()->cookie('academy_theme') === 'dark' ? 'dark' : '' }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('AI Ustoz')) · HUQUQ AI Academy</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="ai-page">
<a href="#ai-main" class="edu-skip-link">{{ __('Asosiy tarkibga o‘tish') }}</a>
<header class="ai-topbar">
    <a href="{{ route('dashboard') }}" class="ai-brand"><span aria-hidden="true">✦</span><strong>{{ $academySettings?->name ?? 'HUQUQ AI Academy' }}</strong></a>
    <nav class="ai-tabs" aria-label="{{ __('Asosiy menyu') }}">
        <a href="{{ route('dashboard') }}">{{ __('Boshqaruv') }}</a>
        <a href="{{ auth()->user()->role->value === 'student' ? route('student.dashboard') : (auth()->user()->role->value === 'mentor' ? route('mentor.students.index') : route('admin.courses.index')) }}">{{ __('Kurslar') }}</a>
        <a href="{{ route('ai.index') }}" class="is-active" aria-current="page">{{ __('AI Ustoz') }}</a>
        <a href="{{ route('library.index') }}">{{ __('Kutubxona') }}</a>
        <a href="{{ route('schedule.index') }}">{{ __('Jadval') }}</a>
        <a href="{{ route('announcements.index') }}">{{ __('E’lonlar') }}</a>
    </nav>
    <div class="ai-header-actions">
        <a href="{{ route('search') }}" class="ai-icon-button" aria-label="{{ __('Qidirish') }}"><x-nav-icon name="search" /></a>
        <a href="{{ route('notifications.index') }}" class="ai-icon-button" aria-label="{{ __('Bildirishnomalar') }}"><x-nav-icon name="notification" /></a>
        <details class="ai-profile"><summary><span class="ai-avatar">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span><span class="ai-profile-name"><strong>{{ auth()->user()->name }}</strong><small>{{ __(auth()->user()->role->value) }}</small></span><span aria-hidden="true">⌄</span></summary>
            <div class="ai-profile-menu">
                <form method="POST" action="{{ route('locale.update') }}">@csrf<label for="ai-locale">{{ __('Interfeys tili') }}</label><select id="ai-locale" name="locale">@foreach (['uz-latin' => 'O‘zbekcha', 'uz-cyr' => 'Ўзбекча', 'ru' => 'Русский'] as $key => $label)<option value="{{ $key }}" @selected(app()->getLocale() === $key)>{{ $label }}</option>@endforeach</select><button>{{ __('Tilni tanlash') }}</button></form>
                <form method="POST" action="{{ route('logout') }}">@csrf<button>{{ __('Tizimdan chiqish') }}</button></form>
            </div>
        </details>
    </div>
</header>
<main id="ai-main" class="ai-main">@include('layouts.partials.feedback') @yield('content')</main>
@stack('scripts')
</body>
</html>
