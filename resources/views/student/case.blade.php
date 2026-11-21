@extends('layouts.student')
@section('title', $legalCase->assignment->title)
@section('content')
<a href="{{ route('student.cases.index') }}" class="text-blue-700 underline">{{ __('Kazuslar kutubxonasi') }}</a>
<h1 class="mt-3 text-2xl font-semibold">{{ $legalCase->assignment->title }}</h1>
<p>{{ $legalCase->assignment->course->title }} · {{ $legalCase->assignment->max_score }} {{ __('ball') }}</p>
@if ($errors->any())<ul role="alert" class="mt-4 text-red-700">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
<div class="mt-6 grid items-start gap-6 lg:grid-cols-2">
    @include('student.case-content')
    <div class="flex flex-col gap-6">
        <section class="ai-assist-panel">
            <div class="ai-assist-heading"><img src="{{ asset('images/ai-tutor-mascot.png') }}" width="72" height="72" alt=""><h2>{{ __('AI bilan tahlil qilish') }}</h2></div>
            <p class="mt-2 text-sm">{{ __('O‘z fikringizni yozing. AI yo‘naltiruvchi savollar beradi. Bu rasmiy baho emas.') }}</p>
            <form method="POST" action="{{ route('student.cases.feedback', $legalCase) }}" class="mt-4 flex flex-col gap-3">
                @csrf
                <label for="analysis">{{ __('Dastlabki tahlilingiz') }}</label>
                <textarea id="analysis" name="analysis" required minlength="10" maxlength="4000" rows="5" class="rounded-lg border p-2">{{ old('analysis') }}</textarea>
                <button class="rounded-lg bg-blue-700 px-4 py-2 text-white">{{ __('Yo‘naltiruvchi tavsiya olish') }}</button>
            </form>
            @foreach ($feedback as $item)
                <div class="mt-4 rounded-lg bg-blue-50 p-4">
                    <p class="whitespace-pre-line text-sm">{{ $item->analysis }}</p>
                    <p class="mt-3 whitespace-pre-line">{{ $item->feedback }}</p>
                    <p class="mt-2 text-xs">{{ $item->created_at->format('d.m.Y H:i') }} {{ __('· AI tavsiyasi') }}</p>
                </div>
            @endforeach
            {{ $feedback->links() }}
        </section>
        <section class="rounded-2xl bg-white p-6">
            <h2 class="font-semibold">{{ __('Mentorga topshirish') }}</h2>
            <p class="mt-2 text-sm">{{ __('Muddat:') }} {{ $legalCase->assignment->deadline?->format('d.m.Y H:i') ?? __('Belgilanmagan') }} {{ __('· Urinishlar:') }} {{ $legalCase->assignment->submission_attempts }}</p>
            @if ($legalCase->assignment->rubric)<p class="mt-2 whitespace-pre-line">{{ __('Mezonlar:') }} {{ $legalCase->assignment->rubric }}</p>@endif
            <form method="POST" action="{{ route('student.assignments.submit', $legalCase->assignment) }}" class="mt-4 flex flex-col gap-3">
                @csrf
                <label for="case-answer">{{ __('Yakuniy tahlilingiz') }}</label>
                <textarea id="case-answer" name="text" required minlength="10" rows="6" class="rounded-lg border p-2">{{ old('text') }}</textarea>
                <button class="rounded-lg bg-blue-700 px-4 py-2 text-white">{{ __('Yuborish') }}</button>
            </form>
            @foreach ($submissions as $submission)
                <div class="mt-4 border-t pt-4">
                    <p>{{ __('Urinish') }} {{ $submission->attempt }} · {{ __($submission->status) }} {{ __('· Ball:') }} {{ $submission->score ?? __('Kutilmoqda') }}</p>
                    <p class="mt-2 whitespace-pre-line text-sm">{{ $submission->text }}</p>
                    @foreach ($submission->reviews as $review)
                        <p class="mt-2 text-sm">{{ $review->mentor->name }} · {{ $review->score }} {{ __('ball ·') }} {{ __($review->decision) }}: {{ $review->comment }}</p>
                    @endforeach
                </div>
            @endforeach
        </section>
    </div>
</div>
@endsection
