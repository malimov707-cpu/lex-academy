@extends('layouts.student')

@section('title', __('Topshiriqlar'))

@section('content')
@if ($errors->any())<p role="alert" class="mb-4 text-red-700">{{ $errors->first() }}</p>@endif
<h1 class="text-2xl font-semibold">{{ __('Topshiriqlarim') }}</h1>

<div class="mt-6 space-y-4">
    @forelse ($assignments as $assignment)
        @php $submission = $submissions->get($assignment->id); $review = $submission?->reviews->last(); @endphp
        <div class="edu-card p-5">
            <div class="flex justify-between items-start">
                <div>
                    <div class="font-semibold">{{ $assignment->title }}</div>
                    @if ($assignment->legalCase)<a class="text-blue-700 underline" href="{{ route('student.cases.show', $assignment->legalCase) }}">{{ __('Kazusni ochish') }}</a>@endif
                    <div class="text-sm text-[#0f1e3d]/60 dark:text-white/60">{{ $assignment->course->title }} {{ __('· max') }} {{ $assignment->max_score }} {{ __('ball') }}</div>
                    @if ($assignment->deadline)
                        <div class="text-sm {{ now()->gt($assignment->deadline) ? 'text-red-600' : 'text-amber-600' }}">{{ __('Deadline:') }} {{ $assignment->deadline->format('d.m.Y H:i') }}</div>
                    @endif
                </div>
                @if ($submission)
                    <span class="edu-badge {{ $submission->status === 'approved' ? 'edu-badge-success' : ($submission->status === 'revision_requested' ? 'edu-badge-warning' : 'edu-badge-info') }}">
                        {{ __($submission->status) }}
                    </span>
                @endif
            </div>

            @if ($review)
                <div class="mt-3 rounded-xl bg-[#f7f5f0] p-4 text-sm">
                    <span class="font-medium">{{ __('Mentor:') }} {{ $review->mentor->name }}</span> {{ __('· Ball:') }} <strong>{{ $review->score }}</strong>
                    @if ($review->comment)<div class="mt-1 text-[#0f1e3d]/70 dark:text-white/70">{{ $review->comment }} {{ $review->annotations['notes'] ?? '' }}</div>@endif
                </div>
            @endif

            <details class="mt-4 text-sm"><summary>{{ __('Yuborish tarixi') }}</summary>@foreach ($history->get($assignment->id, collect()) as $past)<article class="mt-3 rounded-lg bg-slate-50 p-4"><p>{{ __('Urinish') }} {{ $past->attempt }} · {{ __($past->status) }}</p><p class="mt-2 whitespace-pre-line">{{ $past->text }}</p>@foreach ($past->files as $file)<a class="mt-2 block text-blue-700 underline" href="{{ route('submission-files.download', $file) }}">{{ $file->original_name }}</a>@endforeach @foreach ($past->reviews as $pastReview)<p class="mt-2">{{ $pastReview->mentor->name }} · {{ $pastReview->score }} · {{ $pastReview->comment }} {{ $pastReview->annotations['notes'] ?? '' }}</p>@endforeach</article>@endforeach</details>
            <form method="POST" action="{{ route('student.assignments.submit', $assignment) }}" enctype="multipart/form-data" class="mt-3 space-y-2">
                @csrf
                <textarea name="text" rows="3" placeholder="{{ __('Javobingiz (kamida 10 belgi)') }}" class="edu-input">{{ $submission?->status === 'in_progress' ? $submission->text : old('text') }}</textarea>
                <button formaction="{{ route('student.assignments.draft', $assignment) }}" formnovalidate class="rounded border px-4 py-2 text-sm">{{ __('Matn qoralamasini saqlash') }}</button>
                <input type="file" name="files[]" multiple class="w-full text-sm">
                <button class="edu-btn edu-btn-primary edu-btn-sm">{{ __('Yuborish') }}</button>
            </form>
        </div>
    @empty
        <div class="rounded-2xl border border-dashed border-[#0f1e3d]/20 p-8 text-center text-[#0f1e3d]/50 dark:text-white/50">{{ __('Topshiriq yo\'q') }}</div>
    @endforelse
</div>
@endsection
