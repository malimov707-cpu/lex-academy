<!DOCTYPE html>
<html lang="{{ app()->getLocale() === 'uz-cyr' ? 'uz-Cyrl' : (app()->getLocale() === 'ru' ? 'ru' : 'uz-Latn') }}" data-theme="dark" class="h-full dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#071329">
    <title>@yield('title', __('Virtual sud')) · {{ $academySettings?->name ?? 'HUQUQ AI Academy' }}</title>
    @vite(['resources/css/app.css', 'resources/js/virtual-courtroom.js'])
</head>
<body class="courtroom-page">
<a href="#courtroom-main" class="edu-skip-link">{{ __('Sud boshqaruviga o‘tish') }}</a>
<main id="courtroom-main" tabindex="-1">
    @include('layouts.partials.feedback')
    @yield('content')
</main>
@stack('scripts')
</body>
</html>
