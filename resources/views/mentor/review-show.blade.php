@extends('layouts.app')

@section('title', __('Topshiriqni tekshirish'))

@section('content')
<div class="staff-shell">
    @if ($errors->any())<p role="alert" class="mb-4 text-red-600">{{ $errors->first() }}</p>@endif

    {{-- ===== SARLAVHA ===== --}}
    <section class="staff-hero">
        <div class="staff-hero-copy">
            <span class="staff-hero-badge"><i aria-hidden="true"></i>{{ __('Tekshiruv') }}</span>
            <h1>{{ $submission->assignment->title }}</h1>
            <div class="staff-meta-strip" style="color:rgb(255 255 255/.8)">
                <span><span class="staff-avatar" style="width:26px;height:26px;font-size:10px;color:#fff;background:rgb(255 255 255/.16);border-color:transparent">{{ mb_strtoupper(mb_substr($submission->student->name, 0, 1)) }}</span>{{ $submission->student->name }}</span>
                <span>{{ $submission->assignment->course->title }}</span>
                <span>{{ __('attempt') }} {{ $submission->attempt }}</span>
                <span>{{ $submission->submitted_at->format('d.m.Y H:i') }}</span>
            </div>
        </div>
        @if (in_array($submission->status, ['submitted', 'resubmitted']))
            <form method="POST" action="{{ route('mentor.reviews.claim', $submission) }}" style="position:relative;z-index:1">@csrf
                <button class="staff-hero-cta">{{ __('Tekshirishni boshlash') }}<span aria-hidden="true">↗</span></button>
            </form>
        @endif
        <span class="staff-hero-mark" aria-hidden="true">§</span>
    </section>

    <div class="staff-review-grid mt-6">
        <div class="staff-stack">
            @if ($submission->assignment->legalCase)
                @include('student.case-content', ['legalCase' => $submission->assignment->legalCase])
                @foreach ($caseFeedback as $item)
                    <div class="staff-ai-note">
                        <h2><img src="{{ asset('images/ai-tutor-mascot.png') }}" width="44" height="44" alt="">{{ __('AI yo‘naltirishi (rasmiy baho emas)') }}</h2>
                        <p class="whitespace-pre-line">{{ $item->analysis }}</p>
                        <p class="mt-3 whitespace-pre-line">{{ $item->feedback }}</p>
                    </div>
                @endforeach
            @endif

            <section class="staff-panel">
                <div class="staff-panel-head"><h2>{{ __('Ko\'rsatmalar') }}</h2></div>
                <p class="text-sm whitespace-pre-line" style="color:var(--edu-text-muted);line-height:1.8">{{ $submission->assignment->instructions }}</p>
                @if ($submission->assignment->rubric)
                    <h3 class="mt-5 text-sm font-semibold">{{ __('Rubric') }}</h3>
                    <p class="mt-1 text-sm whitespace-pre-line" style="color:var(--edu-text-muted)">{{ $submission->assignment->rubric }}</p>
                @endif
            </section>

            <section class="staff-panel">
                <div class="staff-panel-head"><h2>{{ __('Talaba javobi') }}</h2></div>
                <p class="whitespace-pre-line text-sm leading-relaxed">{{ $submission->text }}</p>
                @if ($submission->files->isNotEmpty())
                    <ul class="mt-4 space-y-2 text-sm">
                        @foreach ($submission->files as $file)
                            <li>
                                <a class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium" style="background:var(--staff-blue-soft,#eaf2ff);color:#1765e9" href="{{ route('submission-files.download', $file) }}">
                                    <x-nav-icon name="assignments" style="width:15px;height:15px" />{{ $file->original_name }}
                                    <small style="color:#7e8ba1">({{ $file->size_kb }} {{ __('KB') }})</small>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            @if ($submission->reviews->isNotEmpty())
                <section class="staff-panel">
                    <div class="staff-panel-head"><h2>{{ __('Review tarixi') }}</h2></div>
                    <ul class="staff-stack">
                        @foreach ($submission->reviews as $review)
                            <li class="staff-row !py-3">
                                <span class="staff-avatar" style="width:31px;height:31px;font-size:11px" aria-hidden="true">{{ mb_strtoupper(mb_substr($review->mentor->name, 0, 1)) }}</span>
                                <span class="staff-row-main">
                                    <strong>{{ $review->mentor->name }}</strong>
                                    <small>@if($review->comment){{ $review->comment }} {{ $review->annotations['notes'] ?? '' }}@endif</small>
                                </span>
                                <span class="edu-badge {{ $review->decision === 'approved' ? 'edu-badge-success' : ($review->decision === 'rejected' ? 'edu-badge-danger' : 'edu-badge-warning') }}">{{ $review->score }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>

        <aside class="staff-stack">
            {{-- ===== AI tahlili ===== --}}
            <section id="ai-review" class="staff-ai-note">
                <h2><img src="{{ asset('images/ai-tutor-mascot.png') }}" width="56" height="56" alt="">{{ __('AI dastlabki tahlili') }}</h2>
                <p>{{ __('AI faqat matnni ko‘radi. Fayllarni mentor tekshiradi; AI rasmiy baho bermaydi.') }}</p>
                @if ($submission->ai_analysis)<p class="whitespace-pre-line">{{ $submission->ai_analysis['content'] }}</p>@endif
                <form method="POST" action="{{ route('mentor.submissions.guidance', $submission) }}" class="mt-4">@csrf
                    <button class="edu-btn edu-btn-primary edu-btn-sm w-full">{{ __('AI tahlili olish') }}</button>
                </form>
            </section>

            {{-- ===== Baholash formasi ===== --}}
            <form method="POST" action="{{ route('mentor.reviews.store', $submission) }}" class="staff-panel staff-form-grid">
                @csrf
                <div class="staff-panel-head !mb-0"><h2>{{ __('Baholash') }}</h2></div>
                <div>
                    <label for="review-score">{{ __('Ball (max') }} {{ $submission->assignment->max_score }})</label>
                    <input id="review-score" name="score" type="number" step="0.5" min="0" max="{{ $submission->assignment->max_score }}" required class="edu-input">
                </div>
                <div>
                    <label for="review-comment">{{ __('Izoh') }}</label>
                    <textarea id="review-comment" name="comment" rows="4" class="edu-input"></textarea>
                </div>
                <div>
                    <label for="review-decision">{{ __('Qaror') }}</label>
                    <select id="review-decision" name="decision" class="edu-input">
                        <option value="approved">{{ __('Tasdiqlash') }}</option>
                        <option value="rejected">{{ __('Rad etish') }}</option>
                        <option value="revision_requested">{{ __('Qayta ishlashga yuborish') }}</option>
                    </select>
                </div>
                <div>
                    <label for="review-annotations">{{ __('Xatolar va tavsiyalar') }}</label>
                    <textarea id="review-annotations" name="annotations" rows="4" class="edu-input"></textarea>
                </div>
                <button class="edu-btn edu-btn-primary w-full !py-3">{{ __('Saqlash') }}</button>
            </form>
        </aside>
    </div>
</div>
@endsection
