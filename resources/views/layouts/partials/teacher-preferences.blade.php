<dialog id="teacher-preferences" class="teacher-preferences" aria-labelledby="teacher-preferences-title">
    <div class="mb-5 flex items-center justify-between gap-4">
        <h2 id="teacher-preferences-title" class="text-xl font-semibold">{{ __('Sozlamalar') }}</h2>
        <form method="dialog">
            <button type="submit" class="teacher-header-button" aria-label="{{ __('Yopish') }}"><x-nav-icon name="close" /></button>
        </form>
    </div>
    <form method="POST" action="{{ route('locale.update') }}">
        @csrf
        <label for="teacher-preferences-locale">{{ __('Interfeys tili') }}</label>
        <select id="teacher-preferences-locale" name="locale" onchange="this.form.requestSubmit()">
            @foreach (['uz-latin' => 'O‘zbekcha', 'uz-cyr' => 'Ўзбекча', 'ru' => 'Русский'] as $value => $label)
                <option value="{{ $value }}" @selected(app()->getLocale() === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </form>
    <form method="POST" action="{{ route('theme.update') }}">
        @csrf
        <label for="teacher-preferences-theme">{{ __('Mavzu') }}</label>
        <select id="teacher-preferences-theme" name="theme" onchange="this.form.requestSubmit()">
            <option value="light" @selected($currentTheme !== 'dark')>{{ __('Yorug‘ mavzu') }}</option>
            <option value="dark" @selected($currentTheme === 'dark')>{{ __('Tungi mavzu') }}</option>
        </select>
    </form>
</dialog>
