@extends('layouts.app')

@section('title', __('Review Workspace'))

@section('content')
<div class="staff-shell">
    {{-- ===== SARLAVHA ===== --}}
    <section class="staff-hero">
        <div class="staff-hero-copy">
            <span class="staff-hero-badge"><i aria-hidden="true"></i>{{ __('Mentor') }} · Review Workspace</span>
            <h1>{{ __('Tekshirish maydoni') }}</h1>
            <p>{{ __('Yuborilgan ishlarni birma-bir ko‘rib chiqib, ball va izoh qoldiring.') }}</p>
        </div>
        <span class="staff-hero-mark" aria-hidden="true">✓</span>
    </section>

    {{-- ===== TEKSHIRILMAGAN ===== --}}
    <div class="staff-section-title">
        <h2>{{ __('Tekshirilmagan') }}<span class="staff-count-chip">{{ $pending->total() }}</span></h2>
    </div>
    <div class="staff-card-grid">
        @forelse ($pending as $submission)
            <a href="{{ route('mentor.reviews.show', $submission) }}" class="staff-card">
                <div class="staff-card-top">
                    <span class="staff-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($submission->student->name, 0, 1)) }}</span>
                    <div>
                        <strong>{{ $submission->student->name }}</strong>
                        <small>{{ $submission->assignment->course->title }}</small>
                    </div>
                    <span class="edu-badge edu-badge-warning" style="margin-left:auto">{{ __('Kutilmoqda') }}</span>
                </div>
                <h3>{{ $submission->assignment->title }}</h3>
                <div class="staff-card-meta">
                    <strong>{{ __('Tekshirish') }} →</strong>
                    <time>{{ $submission->submitted_at->format('d.m.Y H:i') }}</time>
                </div>
            </a>
        @empty
            <div class="staff-empty" style="grid-column:1/-1">{{ __('Hamma ish tekshirilgan 👏') }}</div>
        @endforelse
    </div>
    <div class="mt-4">{{ $pending->links() }}</div>

    {{-- ===== TEKSHIRILGAN ===== --}}
    <div class="staff-section-title">
        <h2>{{ __('Tekshirilgan') }}<span class="staff-count-chip">{{ $reviewed->total() }}</span></h2>
    </div>
    <section class="staff-panel">
        <ul class="staff-stack">
            @forelse ($reviewed as $submission)
                <li>
                    <a href="{{ route('mentor.reviews.show', $submission) }}" class="staff-row">
                        <span class="staff-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($submission->student->name, 0, 1)) }}</span>
                        <span class="staff-row-main">
                            <strong>{{ $submission->assignment->title }}</strong>
                            <small>{{ $submission->student->name }}</small>
                        </span>
                        <span class="edu-badge {{ $submission->status === 'graded' ? 'edu-badge-success' : 'edu-badge-info' }} staff-row-action">
                            {{ __($submission->status) }}{{ $submission->score !== null ? ' · '.$submission->score : '' }}
                        </span>
                    </a>
                </li>
            @empty
                <li class="staff-empty">{{ __('Tarix bo\'sh') }}</li>
            @endforelse
        </ul>
    </section>
    <div class="mt-4">{{ $reviewed->links() }}</div>
</div>
@endsection
