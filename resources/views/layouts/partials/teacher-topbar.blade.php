<button type="button" class="teacher-header-button teacher-menu-button edu-only-mobile-wide" data-navigation-toggle aria-controls="academy-navigation" aria-expanded="false" aria-label="{{ __('Menyuni ochish') }}">
    <x-nav-icon name="menu" />
</button>

<form method="GET" action="{{ route('search') }}" role="search" class="teacher-search">
    <label for="global-search" class="sr-only">{{ __('Global qidiruv') }}</label>
    <button type="submit" aria-label="{{ __('Qidiruv') }}"><x-nav-icon name="search" /></button>
    <input id="global-search" type="search" name="q" maxlength="100" value="{{ request()->routeIs('search') ? request('q') : '' }}" placeholder="{{ __('Qidiruv... (kurs, talaba, ssenariy)') }}">
</form>

<div class="teacher-header-actions">
    <a href="{{ route('notifications.index') }}" class="teacher-header-button" aria-label="{{ __('Bildirishnomalar: :count ta yangi', ['count' => $unread]) }}">
        <x-nav-icon name="notification" />
        @if ($unread > 0)
            <span class="teacher-unread" aria-hidden="true">{{ $unread > 99 ? '99+' : $unread }}</span>
        @endif
    </a>
    <form method="POST" action="{{ route('theme.update') }}">
        @csrf
        <input type="hidden" name="theme" value="{{ $currentTheme === 'dark' ? 'light' : 'dark' }}">
        <button type="submit" class="teacher-header-button" aria-label="{{ $currentTheme === 'dark' ? __('Yorug‘ mavzu') : __('Tungi mavzu') }}">
            <x-nav-icon :name="$currentTheme === 'dark' ? 'settings' : 'moon'" />
        </button>
    </form>
    <form method="POST" action="{{ route('locale.update') }}" class="teacher-header-locale">
        @csrf
        <label for="ui-locale" class="sr-only">{{ __('Interfeys tili') }}</label>
        <select id="ui-locale" name="locale" onchange="this.form.requestSubmit()">
            @foreach (['uz-latin' => 'UZ', 'uz-cyr' => 'ЎЗ', 'ru' => 'RU'] as $value => $label)
                <option value="{{ $value }}" @selected(app()->getLocale() === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <x-nav-icon name="chevron-down" />
    </form>
</div>
