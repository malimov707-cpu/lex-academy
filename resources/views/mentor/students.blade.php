@extends('layouts.app')
@section('title', __('Biriktirilgan talabalar'))
@section('content')
<div class="staff-shell">
    <section class="staff-hero">
        <div class="staff-hero-copy">
            <span class="staff-hero-badge"><i aria-hidden="true"></i>{{ __('Mentor') }} · {{ __('Talabalar') }}</span>
            <h1>{{ __('Biriktirilgan talabalar') }}</h1>
            <p>{{ __('Har bir talaba kurs kontekstida biriktirilgan — bosib, muloqot va natijalarni ko‘ring.') }}</p>
        </div>
        <span class="staff-hero-mark" aria-hidden="true">⚖</span>
    </section>

    <div class="staff-card-grid mt-6">
        @forelse ($assignments as $assignment)
            <a href="{{ route('mentor.students.show', $assignment) }}" class="staff-card">
                <div class="staff-card-top">
                    <span class="staff-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($assignment->student->name, 0, 1)) }}</span>
                    <div>
                        <strong>{{ $assignment->student->name }}</strong>
                        <small>{{ __('Talaba') }}</small>
                    </div>
                </div>
                <h3>{{ $assignment->course->title }}</h3>
                <div class="staff-card-meta">
                    <strong>{{ __('Ochish') }} →</strong>
                    <time>{{ $assignment->created_at?->format('d.m.Y') }}</time>
                </div>
            </a>
        @empty
            <div class="staff-empty" style="grid-column:1/-1">{{ __('Biriktirish mavjud emas.') }}</div>
        @endforelse
    </div>
    {{ $assignments->links() }}
</div>
@endsection
