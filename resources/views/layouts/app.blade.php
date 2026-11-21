@php
    $isControlCenter = request()->routeIs('admin.control-center');
    $isSuperAdmin = auth()->user()?->hasRole(\App\Enums\UserRole::SuperAdmin) ?? false;
    $isTeacher = auth()->user()?->hasRole(\App\Enums\UserRole::Teacher) ?? false;
    $currentTheme = request()->cookie('academy_theme', $isSuperAdmin ? 'dark' : 'light');
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() === 'uz-cyr' ? 'uz-Cyrl' : (app()->getLocale() === 'ru' ? 'ru' : 'uz-Latn') }}"
      class="h-full"
      data-theme="{{ $currentTheme === 'dark' ? 'dark' : 'light' }}">
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
    <meta name="theme-color" content="#0f1e3d">
    <title>@yield('title', 'HUQUQ AI Academy')</title>
    @vite('resources/js/app.js')
    @vite('resources/css/app.css')
</head>
<body class="min-h-full antialiased {{ $isSuperAdmin ? 'cc-admin-frame' : '' }} {{ $isControlCenter ? 'cc-page' : '' }} {{ $isTeacher ? 'teacher-frame' : '' }}">
    <a href="#main-content" class="edu-skip-link">{{ __('Asosiy tarkibga o‘tish') }}</a>

    @guest
        {{-- ===== Mehmon uchun sodda sarlavha ===== --}}
        <header class="edu-topbar">
            <a href="{{ route('login') }}" class="edu-brand" style="background:none;border:0;padding:0">
                <span class="edu-brand-mark" aria-hidden="true">§</span>
                <span class="edu-brand-copy" style="color:var(--edu-text)">
                    <strong>{{ $academySettings?->name ?? 'HUQUQ AI Academy' }}</strong>
                    <small style="color:var(--edu-gold)">{{ __('Huquqiy ta’lim platformasi') }}</small>
                </span>
            </a>
            <div class="ml-auto flex items-center gap-2">
                <a href="{{ route('theme.update') }}" onclick="event.preventDefault();document.getElementById('guest-theme').submit();"
                   class="edu-icon-button" aria-label="{{ __('Tungi mavzu') }}">🌙</a>
                <form id="guest-theme" method="POST" action="{{ route('theme.update') }}" class="hidden">@csrf
                    <input type="hidden" name="theme" value="{{ $currentTheme === 'dark' ? 'light' : 'dark' }}">
                </form>
                <a href="{{ route('login') }}" class="edu-btn edu-btn-primary">{{ __('Kirish') }}</a>
            </div>
        </header>
        <main id="main-content" tabindex="-1" class="mx-auto w-full max-w-6xl px-4 py-10">
            @include('layouts.partials.feedback')
            @yield('content')
        </main>
        @stack('scripts')
    </body>
</html>
    @else

    @php $navUser = auth()->user(); @endphp

    {{-- ===== Mobil drawer scrim ===== --}}
    <button type="button" class="edu-drawer-scrim" data-navigation-toggle hidden aria-label="{{ __('Menyuni yopish') }}" tabindex="-1"></button>

    <div class="edu-shell flex min-h-screen">
        {{-- ===== SIDEBAR ===== --}}
        <aside id="academy-navigation"
               class="edu-sidebar fixed inset-y-0 left-0 z-40 -translate-x-full transition-transform duration-200 ease-out lg:translate-x-0 print:hidden"
               data-navigation-panel
               aria-label="{{ __('Asosiy menyu') }}">
            @if ($isTeacher)
                @include('layouts.partials.teacher-navigation')
            @else
            <a href="{{ route('dashboard') }}" class="edu-brand">
                <span class="edu-brand-mark" aria-hidden="true">@if($isSuperAdmin)<x-nav-icon name="scale" />@else § @endif</span>
                <span class="edu-brand-copy">
                    <strong>{{ $isSuperAdmin ? 'LEGAL EDU' : ($academySettings?->name ?? 'HUQUQ AI Academy') }}</strong>
                    <small>{{ $isSuperAdmin ? 'Control Center' : __('Legal · EdTech') }}</small>
                </span>
            </a>

            <nav class="edu-sidebar-nav" aria-label="{{ __('Akademiya bo‘limlari') }}">
                @include('layouts.navigation')
            </nav>

            <div class="edu-sidebar-footer">
                @if($isSuperAdmin)
                    <div class="cc-admin-access"><span aria-hidden="true">♛</span><div><strong>Super Admin</strong><small>{{ __('To‘liq boshqaruv') }}</small></div></div>
                    <div class="cc-admin-profile"><span>{{ mb_strtoupper(mb_substr($navUser->name, 0, 1)) }}</span><div><strong>{{ $navUser->name }}</strong><small>{{ __('Super administrator') }}</small></div></div>
                @endif
                <div class="edu-sidebar-note">
                    <span aria-hidden="true">◇</span>
                    <span>{{ __('Bilim — himoyaning eng kuchli quroli.') }}</span>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="edu-logout-button">
                        <x-nav-icon name="logout" />
                        <span>{{ __('Tizimdan chiqish') }}</span>
                    </button>
                </form>
            </div>
            @endif
        </aside>

        {{-- ===== WORKSPACE ===== --}}
        <div class="edu-workspace flex min-w-0 flex-1 flex-col">
            <header class="edu-topbar">
                @if ($isTeacher)
                    @include('layouts.partials.teacher-topbar')
                @else
                <button type="button" class="edu-icon-button edu-only-mobile-wide"
                        data-navigation-toggle aria-controls="academy-navigation" aria-expanded="false">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                         aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                    <span class="sr-only">{{ __('Menyuni ochish') }}</span>
                </button>

                <div class="edu-topbar-title hidden min-w-0 flex-col lg:flex">
                    <span class="truncate text-[0.7rem] font-medium uppercase tracking-[0.14em]" style="color:var(--edu-text-muted)">
                        HUQUQ AI Academy
                    </span>
                    <span class="truncate text-sm font-semibold">@yield('title', __('Boshqaruv'))</span>
                </div>

                {{-- Global qidiruv --}}
                <form method="GET" action="{{ route('search') }}" role="search"
                      class="edu-topbar-search">
                    <label for="global-search" class="sr-only">{{ __('Global qidiruv') }}</label>
                    <div class="relative flex-1">
                        <span class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2" style="color:var(--edu-text-muted)">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true" class="h-4 w-4">
                                <circle cx="11" cy="11" r="7"/><path d="M20 20l-4.2 -4.2"/>
                            </svg>
                        </span>
                        <input id="global-search" type="search" name="q" value="{{ request()->routeIs('search') ? request('q') : '' }}"
                               placeholder="{{ $isSuperAdmin ? __('Platforma bo‘yicha qidiruv…') : __('Kurs, dars, foydalanuvchi…') }}"
                               class="edu-input !py-1.5 !pl-9 !text-sm">
                        @if($isSuperAdmin)<kbd class="cc-search-key" aria-hidden="true">⌘ K</kbd>@endif
                    </div>
                </form>

                <div class="ml-auto flex items-center gap-2">
                    <a href="{{ route('search') }}" class="edu-icon-button edu-only-mobile" aria-label="{{ __('Qidiruv') }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true" class="h-[18px] w-[18px]">
                            <circle cx="11" cy="11" r="7"/><path d="M20 20l-4.2 -4.2"/>
                        </svg>
                    </a>
                    <form method="POST" action="{{ route('locale.update') }}" class="{{ $isSuperAdmin ? 'cc-locale-form' : 'hidden items-center sm:flex' }}">@csrf
                        <label for="ui-locale" class="sr-only">{{ __('Interfeys tili') }}</label>
                        <select id="ui-locale" name="locale" onchange="this.form.submit()"
                                class="edu-input !w-auto !py-1.5 !text-xs">
                            @foreach (['uz-latin' => 'UZ', 'uz-cyr' => 'ЎЗ', 'ru' => 'RU'] as $value => $label)
                                <option value="{{ $value }}" @selected(app()->getLocale() === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </form>

                    <form method="POST" action="{{ route('theme.update') }}">@csrf
                        <input type="hidden" name="theme" value="{{ $currentTheme === 'dark' ? 'light' : 'dark' }}">
                        <button type="submit" class="edu-icon-button"
                                aria-label="{{ $currentTheme === 'dark' ? __('Yorug‘ mavzu') : __('Tungi mavzu') }}">
                            {{ $currentTheme === 'dark' ? '☀️' : '🌙' }}
                        </button>
                    </form>

                    <a href="{{ route('notifications.index') }}" class="edu-icon-button relative shrink-0"
                       aria-label="{{ __('Bildirishnomalar: :count ta yangi', ['count' => $unread]) }}">
                        <x-nav-icon name="notification" />
                        @if ($unread > 0)
                            <span aria-hidden="true" class="edu-badge-count">
                                {{ $unread > 99 ? '99+' : $unread }}
                            </span>
                        @endif
                    </a>

                    <details class="relative">
                        <summary class="flex cursor-pointer list-none items-center gap-2 rounded-[10px] border px-2 py-1.5"
                                 style="border-color:var(--edu-line)">
                            <span class="grid h-7 w-7 place-items-center rounded-full bg-[var(--edu-royal)] text-xs font-semibold text-white">
                                {{ mb_strtoupper(mb_substr($navUser->name, 0, 1)) }}
                            </span>
                            <span class="hidden flex-col leading-tight sm:flex">
                                <strong class="text-xs font-semibold">{{ $navUser->name }}</strong>
                                <small class="text-[0.68rem]" style="color:var(--edu-text-muted)">{{ __($navUser->role->value) }}</small>
                            </span>
                        </summary>
                        <div class="edu-card absolute right-0 z-50 mt-2 w-64 p-4 text-sm">
                            <strong class="block">{{ $navUser->name }}</strong>
                            <span class="block text-xs" style="color:var(--edu-text-muted)">{{ $navUser->email }}</span>
                            <span class="edu-badge edu-badge-info mt-2">{{ __($navUser->role->value) }}</span>
                            <form method="POST" action="{{ route('logout') }}" class="mt-3 border-t pt-3"
                                  style="border-color:var(--edu-line)">
                                @csrf
                                <button type="submit" class="edu-btn edu-btn-ghost edu-btn-sm w-full">{{ __('Tizimdan chiqish') }}</button>
                            </form>
                        </div>
                    </details>
                </div>
                @endif
            </header>

            <main id="main-content" tabindex="-1" class="mx-auto w-full max-w-[1920px] flex-1 px-4 py-6 sm:px-6">
                @include('layouts.partials.feedback')
                @yield('content')

                @if ($academySettings?->support_email || $academySettings?->support_phone)
                    <footer class="mt-10 flex flex-wrap gap-4 border-t pt-5 text-sm" style="border-color:var(--edu-line);color:var(--edu-text-muted)">
                        @if ($academySettings->support_email)
                            <a href="mailto:{{ $academySettings->support_email }}">{{ $academySettings->support_email }}</a>
                        @endif
                        @if ($academySettings->support_phone)
                            <span>{{ $academySettings->support_phone }}</span>
                        @endif
                    </footer>
                @endif
            </main>
        </div>
    </div>

    @if ($isTeacher)
        @include('layouts.partials.teacher-preferences')
    @endif

    {{-- ===== Mobil drawer skripti: scrim, Escape, focus ===== --}}
    <script>
        (function () {
            var panel = document.querySelector('[data-navigation-panel]');
            var scrim = document.querySelector('.edu-drawer-scrim');
            var toggles = document.querySelectorAll('[data-navigation-toggle]');
            if (!panel || !scrim) return;

            var lastFocused = null;

            function isOpen() { return panel.classList.contains('translate-x-0'); }

            function setOpen(open) {
                panel.classList.toggle('translate-x-0', open);
                panel.classList.toggle('-translate-x-full', !open);
                scrim.hidden = !open;
                scrim.classList.toggle('is-visible', open);
                document.body.style.overflow = open ? 'hidden' : '';

                toggles.forEach(function (btn) {
                    if (btn.dataset.navigationToggle !== undefined) {
                        if (btn.getAttribute('aria-expanded') !== null) {
                            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
                        }
                    }
                });

                if (open) {
                    lastFocused = document.activeElement;
                    var first = panel.querySelector('a, button');
                    if (first) first.focus();
                } else if (lastFocused) {
                    lastFocused.focus();
                }
            }

            toggles.forEach(function (btn) {
                btn.addEventListener('click', function () { setOpen(!isOpen()); });
            });

            var preferencesButton = panel.querySelector('[data-teacher-preferences-open]');
            if (preferencesButton) {
                var preferences = document.getElementById('teacher-preferences');
                var navLinks = Array.from(panel.querySelectorAll('[data-teacher-nav-link]'));
                var currentPage = navLinks.find(function (link) { return link.hasAttribute('aria-current'); });
                function updateActiveSection() {
                    var active = navLinks.find(function (link) {
                        return location.hash && link.pathname === location.pathname && link.hash === location.hash;
                    }) || currentPage;
                    navLinks.forEach(function (link) {
                        if (link === active) {
                            link.setAttribute('aria-current', link.hash && link.hash === location.hash ? 'location' : 'page');
                        } else {
                            link.removeAttribute('aria-current');
                        }
                    });
                }
                updateActiveSection();
                window.addEventListener('hashchange', updateActiveSection);
                panel.addEventListener('click', function (event) {
                    if (isOpen() && event.target.closest('a[href*="#"]')) { setOpen(false); }
                });
                preferencesButton.addEventListener('click', function () {
                    if (isOpen()) { setOpen(false); }
                    preferences.showModal();
                });
            }

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && isOpen()) { setOpen(false); }

                // Focus trap (faqat ochiq mobil drawer ichida)
                if (event.key === 'Tab' && isOpen() && window.innerWidth < 1024) {
                    var focusable = panel.querySelectorAll('a[href], button:not([disabled])');
                    if (!focusable.length) return;
                    var first = focusable[0];
                    var last = focusable[focusable.length - 1];

                    if (event.shiftKey && document.activeElement === first) {
                        event.preventDefault(); last.focus();
                    } else if (!event.shiftKey && document.activeElement === last) {
                        event.preventDefault(); first.focus();
                    }
                }
            });

            window.addEventListener('resize', function () {
                if (window.innerWidth >= 1024 && isOpen()) {
                    setOpen(false);
                }
            });
        })();
    </script>
    @stack('scripts')
</body>
</html>
    @endguest
