{{-- Ro'yxatdan o'tish/kirish sahifalari uchun umumiy qobiq — fon rasmi bilan --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() === 'uz-cyr' ? 'uz-Cyrl' : (app()->getLocale() === 'ru' ? 'ru' : 'uz-Latn') }}"
      class="h-full"
      data-theme="{{ request()->cookie('academy_theme') === 'dark' ? 'dark' : 'light' }}">
<head>
    <script>
        // FOUC oldini olish: cookie'dagi mavzuni birinchi renderdan oldin qo'llaymiz
        (function () {
            var theme = document.documentElement.getAttribute('data-theme');
            if (theme === 'dark') { document.documentElement.classList.add('dark'); }
        })();
    </script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'HUQUQ AI Academy')</title>
    @vite('resources/js/app.js')
    @vite('resources/css/app.css')
</head>
<body class="edu-auth-shell min-h-screen">

    {{-- ===== Orqa fon qatlamlari ===== --}}
    <div class="edu-auth-backdrop" aria-hidden="true">
        <div class="edu-auth-backdrop-tint"></div>
    </div>

    <main class="relative z-10 flex min-h-screen flex-col">
        {{-- ===== Yuqori panel: brend + mavzu ===== --}}
        <header class="flex items-center justify-between px-5 py-4 sm:px-8">
            <a href="{{ route('login') }}" class="flex items-center gap-2.5">
                <span class="edu-brand-mark !h-9 !w-9 !text-base" aria-hidden="true">§</span>
                <span class="edu-brand-copy" style="color:#fff">
                    <strong>{{ $academySettings?->name ?? 'HUQUQ AI Academy' }}</strong>
                    <small>{{ __('Legal · EdTech') }}</small>
                </span>
            </a>
            <div class="flex items-center gap-2">
                <form method="POST" action="{{ route('theme.update') }}">@csrf
                    <input type="hidden" name="theme" value="{{ request()->cookie('academy_theme') === 'dark' ? 'light' : 'dark' }}">
                    <button type="submit" class="edu-icon-button"
                            aria-label="{{ request()->cookie('academy_theme') === 'dark' ? __('Yorug‘ mavzu') : __('Tungi mavzu') }}">
                        {{ request()->cookie('academy_theme') === 'dark' ? '☀️' : '🌙' }}
                    </button>
                </form>
            </div>
        </header>

        {{-- ===== Markaziy karta ===== --}}
        <div class="flex flex-1 items-center justify-center px-4 py-8">
            <div class="w-full max-w-[440px]">
                @include('layouts.partials.feedback')
                <div class="edu-auth-card">
                    @yield('content')
                </div>
            </div>
        </div>

        {{-- ===== Pastki: til + mualliflik ===== --}}
        <footer class="flex flex-wrap items-center justify-between gap-3 px-5 pb-5 sm:px-8">
            <form method="POST" action="{{ route('locale.update') }}" class="flex items-center gap-2">@csrf
                <label for="ui-locale" class="sr-only">{{ __('Interfeys tili') }}</label>
                <select id="ui-locale" name="locale" onchange="this.form.submit()"
                        class="edu-auth-locale">
                    @foreach (['uz-latin' => 'O‘zbekcha', 'uz-cyr' => 'Ўзбекча', 'ru' => 'Русский'] as $value => $label)
                        <option value="{{ $value }}" @selected(app()->getLocale() === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </form>
            <p class="text-[0.65rem] text-white/50">
                {{ __('Huquqiy ta’lim platformasi · © :y', ['y' => now()->year]) }}
            </p>
        </footer>
    </main>
    @stack('scripts')
</body>
</html>
