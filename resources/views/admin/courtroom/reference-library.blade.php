@extends('layouts.app')
@section('title', __('Huquqiy manbalar kutubxonasi'))
@section('content')
    <a class="text-sm text-[var(--edu-royal)]" href="{{ route('admin.courtroom.preview', $scenario) }}">← {{ $scenario->title }}</a>
    <h1 class="edu-page-title mt-2">{{ __('Huquqiy manbalar kutubxonasi') }}</h1>
    <p class="edu-page-lead">{{ __('Manbani qoralamaga biriktiring, so‘ng tegishli modda va amaldagi tahrirni tasdiqlang.') }}</p>

    <form method="GET" class="edu-card mt-5 flex flex-wrap items-end gap-3 p-5">
        <label class="edu-field min-w-48 flex-1">
            <span>{{ __('Manba nomi') }}</span>
            <input type="search" class="edu-input" name="q" value="{{ $search }}" maxlength="120">
        </label>
        <button type="submit" class="edu-btn edu-btn-secondary">{{ __('Qidirish') }}</button>
    </form>

    <div class="mt-5 grid gap-4">
        @forelse($sources as $source)
            <article class="edu-card flex flex-wrap items-start justify-between gap-4 p-5">
                <div class="min-w-0 flex-1">
                    <h2 class="font-semibold">{{ $source->title }}</h2>
                    <p class="mt-2 text-sm text-[var(--edu-text-muted)]">{{ Str::limit($source->excerpt, 350) }}</p>
                    <p class="mt-2 text-sm text-[var(--edu-text-muted)]">{{ __('Tekshirilgan sana :date', ['date' => $source->verified_at->format('d.m.Y')]) }}</p>
                    @if(Str::startsWith(Str::lower($source->url ?? ''), 'https://'))
                        <a class="mt-2 inline-block text-sm text-[var(--edu-royal)] underline" href="{{ $source->url }}" target="_blank" rel="noopener noreferrer">{{ __('Rasmiy manba') }} ↗</a>
                    @endif
                </div>
                <form method="POST" action="{{ route('admin.courtroom.references.attach', [$scenario, $source]) }}">
                    @csrf
                    <button type="submit" class="edu-btn edu-btn-primary">{{ __('Qoralamaga biriktirish') }}</button>
                </form>
            </article>
        @empty
            <p class="edu-card p-5">{{ __('Qidiruvga mos tasdiqlangan umumiy manbalar topilmadi.') }}</p>
        @endforelse
    </div>
    <div class="mt-5">{{ $sources->links() }}</div>
@endsection
