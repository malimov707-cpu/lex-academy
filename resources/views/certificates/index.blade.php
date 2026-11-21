@extends(auth()->user()?->hasRole(\App\Enums\UserRole::Student) ? 'layouts.student' : 'layouts.app')
@section('title', __('Sertifikatlar'))
@section('content')
<div class="staff-shell">
    <section class="staff-hero">
        <div class="staff-hero-copy">
            <span class="staff-hero-badge"><i aria-hidden="true"></i>{{ __('Sertifikatlar') }}</span>
            <h1>{{ __('Sertifikatlaringiz') }}</h1>
            <p>{{ __('Tugallangan kurslaringiz uchun rasmiy sertifikatlar — tekshirish kodi bilan.') }}</p>
        </div>
        <span class="staff-hero-mark" aria-hidden="true">✦</span>
    </section>

    <div class="staff-card-grid mt-6">
        @forelse ($certificates as $certificate)
            <a href="{{ route('certificates.show', $certificate) }}" class="staff-card">
                <div class="staff-card-top">
                    <span class="staff-avatar staff-avatar-lg" style="width:40px;height:40px;border-radius:12px;font-size:16px" aria-hidden="true">§</span>
                    <div>
                        <strong>{{ $certificate->course_title }}</strong>
                        <small>{{ $certificate->issued_at->format('d.m.Y') }}</small>
                    </div>
                    <span class="edu-badge {{ $certificate->revoked_at ? 'edu-badge-danger' : 'edu-badge-success' }}" style="margin-left:auto">
                        {{ $certificate->revoked_at ? __('Bekor qilingan') : __('Amaldagi') }}
                    </span>
                </div>
                <div class="staff-card-meta">
                    <strong>{{ __('Ko‘rish') }} →</strong>
                    <span>{{ $certificate->student_name }}</span>
                </div>
            </a>
        @empty
            <div class="staff-empty" style="grid-column:1/-1">{{ __('Hozircha sertifikat yo‘q.') }}</div>
        @endforelse
    </div>
    {{ $certificates->links() }}
</div>
@endsection
