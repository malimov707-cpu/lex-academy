@extends(auth()->user()?->hasRole(\App\Enums\UserRole::Student) ? 'layouts.student' : 'layouts.app')

@section('title', __('Qidiruv'))

@section('content')
<div class="mx-auto max-w-4xl">
    <h1 class="mb-4 text-lg font-semibold">{{ __('Qidiruv') }}</h1>
    <form method="GET" action="{{ route('search') }}" role="search" class="edu-card p-2">
        <div class="flex items-center gap-2">
            <span class="grid h-9 w-9 place-items-center rounded-lg" style="color:var(--edu-text-muted)">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true" class="h-[18px] w-[18px]">
                    <circle cx="11" cy="11" r="7"/><path d="M20 20l-4.2 -4.2"/>
                </svg>
            </span>
            <input type="search" name="q" value="{{ $term }}" autofocus
                   placeholder="{{ __('Kurs, dars yoki foydalanuvchi qidiring…') }}"
                   aria-label="{{ __('Qidiruv') }}"
                   class="edu-input !border-0 !bg-transparent focus:!shadow-none">
            <button type="submit" class="edu-btn edu-btn-primary shrink-0">{{ __('Qidirish') }}</button>
        </div>
    </form>

    <p class="mt-4 text-sm" style="color:var(--edu-text-muted)" aria-live="polite">
        @if ($term === '')
            {{ __('Kamida 2 belgi kiriting.') }}
        @elseif (mb_strlen($term) < 2)
            {{ __('Qidiruv so‘rovi juda qisqa.') }}
        @else
            «{{ $term }}» — {{ __(':count ta natija', ['count' => $total]) }}
        @endif
    </p>

    @forelse ($results as $group)
        <section class="mt-6" aria-label="{{ $group['label'] }}">
            <h2 class="mb-2 text-[0.7rem] font-semibold uppercase tracking-[0.14em]" style="color:var(--edu-gold)">
                {{ $group['label'] }}
            </h2>
            <ul class="edu-card divide-y overflow-hidden" style="border-color:var(--edu-line)">
                @foreach ($group['items'] as $item)
                    <li style="border-color:var(--edu-line)">
                        <a href="{{ $item['url'] }}"
                           class="flex items-center gap-3 px-4 py-3 text-sm transition hover:bg-[var(--edu-surface-muted)]">
                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg"
                                  style="background:var(--edu-surface-muted);color:var(--edu-royal)">
                                <x-nav-icon :name="$item['icon']" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-medium">{{ $item['title'] }}</span>
                                <span class="block truncate text-xs" style="color:var(--edu-text-muted)">{{ $item['meta'] }}</span>
                            </span>
                            <span class="shrink-0" aria-hidden="true" style="color:var(--edu-text-muted)">→</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @empty
        @if ($term !== '' && mb_strlen($term) >= 2)
            <div class="edu-empty mt-6">
                <x-nav-icon name="analytics" class="h-6 w-6 opacity-50" />
                <strong>{{ __('Hech narsa topilmadi') }}</strong>
                <span class="text-sm">{{ __('Boshqa so‘z bilan urinib ko‘ring.') }}</span>
            </div>
        @endif
    @endforelse
</div>
@endsection
