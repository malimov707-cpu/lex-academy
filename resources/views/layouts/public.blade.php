{{-- Public landing sahifalar uchun umumiy qobiq — SEO, dark rejim, responsive nav --}}
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

    <title>@yield('title', 'HUQUQ AI Academy — Huquqni o‘rganing. Kelajakni yarating.')</title>
    <meta name="description" content="@yield('meta_description', 'Zamonaviy huquqiy ta’lim, amaliy mashg’ulotlar, tajribali ustozlar va sun’iy intellekt yordamida huquqiy bilimlaringizni yangi bosqichga olib chiqing.')">
    @yield('meta')

    {{-- Open Graph --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $academySettings?->name ?? 'HUQUQ AI Academy' }}">
    <meta property="og:title" content="@yield('title', 'HUQUQ AI Academy')">
    <meta property="og:description" content="@yield('meta_description', 'Zamonaviy huquqiy ta’lim platformasi.')">
    <meta property="og:image" content="{{ asset('images/justice-court.jpg') }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta name="twitter:card" content="summary_large_image">

    @vite('resources/js/app.js')
    @vite('resources/css/app.css')
</head>
<body class="edu-public min-h-screen">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:bg-white focus:p-3">{{ __('Asosiy tarkibga o‘tish') }}</a>

    {{-- ===== Header / navigatsiya ===== --}}
    <header class="edu-public-header">
        <div class="edu-public-container flex items-center justify-between gap-4 py-3">
            <a href="{{ route('public.home') }}" class="flex items-center gap-2.5 shrink-0">
                <span class="edu-brand-mark" aria-hidden="true">§</span>
                <span class="edu-brand-copy">
                    <strong>{{ $academySettings?->name ?? 'HUQUQ AI Academy' }}</strong>
                    <small>{{ __('Legal · EdTech') }}</small>
                </span>
            </a>

            <nav class="hidden md:flex items-center gap-1" aria-label="{{ __('Asosiy navigatsiya') }}">
                <a href="{{ route('public.home') }}" class="edu-public-nav-link {{ request()->routeIs('public.home') ? 'is-active' : '' }}">{{ __('Bosh sahifa') }}</a>
                <a href="{{ route('public.courses') }}" class="edu-public-nav-link {{ request()->routeIs('public.courses*') ? 'is-active' : '' }}">{{ __('Kurslar') }}</a>
                <a href="{{ route('public.about') }}" class="edu-public-nav-link {{ request()->routeIs('public.about') ? 'is-active' : '' }}">{{ __('Platforma haqida') }}</a>
                <a href="{{ route('public.faq') }}" class="edu-public-nav-link {{ request()->routeIs('public.faq') ? 'is-active' : '' }}">{{ __('Savol-javob') }}</a>
                <a href="{{ route('public.contact') }}" class="edu-public-nav-link {{ request()->routeIs('public.contact') ? 'is-active' : '' }}">{{ __('Aloqa') }}</a>
            </nav>

            <div class="flex">
                <form method="POST" action="{{ route('theme.update') }}">@csrf
                    <input type="hidden" name="theme" value="{{ request()->cookie('academy_theme') === 'dark' ? 'light' : 'dark' }}">
                    <button type="submit" class="edu-icon-button"
                            aria-label="{{ request()->cookie('academy_theme') === 'dark' ? __('Yorug‘ mavzu') : __('Tungi mavzu') }}">
                        {{ request()->cookie('academy_theme') === 'dark' ? '☀️' : '🌙' }}
                    </button>
                </form>
                <a href="{{ route('login') }}" class="edu-btn-outline !py-2 !px-4 !text-[0.85rem] hidden sm:inline-flex">{{ __('Kirish') }}</a>
                <a href="{{ route('register') }}" class="edu-btn-primary !py-2 !px-4 !text-[0.85rem] hidden sm:inline-flex">{{ __('Ro‘yxatdan o‘tish') }}</a>
                <button type="button" class="edu-icon-button md:hidden" data-public-nav-toggle aria-expanded="false" aria-controls="public-mobile-nav" aria-label="{{ __('Menyu') }}">☰</button>
            </div>
        </div>
        <nav id="public-mobile-nav" class="hidden md:hidden border-t border-[var(--edu-border)] px-5 py-3 flex flex-col gap-1" aria-label="{{ __('Mobil navigatsiya') }}">
            <a href="{{ route('public.home') }}" class="edu-public-nav-link">{{ __('Bosh sahifa') }}</a>
            <a href="{{ route('public.courses') }}" class="edu-public-nav-link">{{ __('Kurslar') }}</a>
            <a href="{{ route('public.about') }}" class="edu-public-nav-link">{{ __('Platforma haqida') }}</a>
            <a href="{{ route('public.faq') }}" class="edu-public-nav-link">{{ __('Savol-javob') }}</a>
            <a href="{{ route('public.contact') }}" class="edu-public-nav-link">{{ __('Aloqa') }}</a>
            <a href="{{ route('login') }}" class="edu-public-nav-link">{{ __('Kirish') }}</a>
            <a href="{{ route('register') }}" class="edu-public-nav-link">{{ __('Ro‘yxatdan o‘tish') }}</a>
        </nav>
    </header>

    <main id="main">
        @yield('content')
    </main>

    {{-- ===== Footer ===== --}}
    <footer class="edu-public-footer">
        <div class="edu-public-container grid gap-8 py-12 md:grid-cols-4">
            <div>
                <div class="flex items-center gap-2.5 mb-3">
                    <span class="edu-brand-mark" aria-hidden="true">§</span>
                    <strong>{{ $academySettings?->name ?? 'HUQUQ AI Academy' }}</strong>
                </div>
                <p class="text-sm opacity-70 max-w-xs">{{ __('Huquqiy bilimlarni tizimli o‘rgatuvchi zamonaviy ta’lim platformasi.') }}</p>
            </div>
            <nav aria-label="{{ __('Platforma') }}">
                <h3 class="edu-public-footer-heading">{{ __('Platforma') }}</h3>
                <a href="{{ route('public.about') }}">{{ __('Platforma haqida') }}</a>
                <a href="{{ route('public.courses') }}">{{ __('Kurslar') }}</a>
                <a href="{{ route('public.home') }}#ai-ustoz">{{ __('AI Ustoz') }}</a>
                <a href="{{ route('public.contact') }}">{{ __('Aloqa') }}</a>
            </nav>
            <nav aria-label="{{ __('Hisob') }}">
                <h3 class="edu-public-footer-heading">{{ __('Hisob') }}</h3>
                <a href="{{ route('login') }}">{{ __('Tizimga kirish') }}</a>
                <a href="{{ route('register') }}">{{ __('Ro‘yxatdan o‘tish') }}</a>
            </nav>
            <nav aria-label="{{ __('Huquqiy') }}">
                <h3 class="edu-public-footer-heading">{{ __('Huquqiy') }}</h3>
                <a href="{{ route('public.home') }}#faq">{{ __('Maxfiylik siyosati') }}</a>
                <a href="{{ route('public.home') }}#faq">{{ __('Foydalanish shartlari') }}</a>
            </nav>
        </div>
        <div class="edu-public-container flex flex-wrap items-center justify-between gap-3 py-4 border-t border-white/10">
            <p class="text-[0.75rem] opacity-60">{{ __('Huquqiy ta’lim platformasi · © :y', ['y' => now()->year]) }}</p>
            <form method="POST" action="{{ route('locale.update') }}" class="flex items-center gap-2">@csrf
                <label for="pub-locale" class="sr-only">{{ __('Interfeys tili') }}</label>
                <select id="pub-locale" name="locale" onchange="this.form.submit()" class="edu-auth-locale">
                    @foreach (['uz-latin' => 'O‘zbekcha', 'uz-cyr' => 'Ўзбекча', 'ru' => 'Русский'] as $value => $label)
                        <option value="{{ $value }}" @selected(app()->getLocale() === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </form>
        </div>
    </footer>

    @stack('scripts')
    <script>
        // Mobil navigatsiya
        (function () {
            var toggle = document.querySelector('[data-public-nav-toggle]');
            var nav = document.getElementById('public-mobile-nav');
            if (toggle && nav) {
                toggle.addEventListener('click', function () {
                    var hidden = nav.classList.toggle('hidden');
                    toggle.setAttribute('aria-expanded', String(!hidden));
                });
            }
        })();
    </script>
</body>
</html>
