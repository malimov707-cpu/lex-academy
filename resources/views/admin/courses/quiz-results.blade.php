@extends('layouts.app')
@section('title', __('Test natijalari'))
@section('content')
<h1 class="text-2xl font-semibold">{{ $quiz->title }} {{ __('— natijalar') }}</h1>
<p class="mt-2">{{ __('Yakuniy baholangan urinishlar o‘rtachasi:') }} {{ $average }}%</p>
@if ($errors->any())<p class="text-red-700" role="alert">{{ $errors->first() }}</p>@endif
<div class="mt-6 flex flex-col gap-4">
@forelse ($attempts as $attempt)
    <article class="rounded-2xl bg-white p-6">
        <h2 class="font-semibold">{{ $attempt->student->name }} {{ __('· Urinish') }} {{ $attempt->attempt }} · {{ $attempt->score }}%</h2>
        <form method="POST" action="{{ route('admin.quizzes.grade', $attempt) }}" class="mt-4 flex flex-col gap-3">
            @csrf
            @foreach ($attempt->answers as $answer)
                <div class="border-t pt-3"><p>{{ $answer->question->text }}</p><p class="whitespace-pre-line text-sm">{{ $answer->text_answer }}</p>
                @if (in_array($answer->question->type, ['long_answer', 'scenario', 'legal_case']))
                    <label class="text-sm">{{ __('Ball /') }} {{ $answer->question->points }}<input name="scores[{{ $answer->id }}]" type="number" step="0.01" min="0" max="{{ $answer->question->points }}" value="{{ $answer->points_earned }}" required class="ml-2 rounded-lg border p-2"></label>
                @else <p class="text-sm">{{ $answer->points_earned }} / {{ $answer->question->points }}</p> @endif
                </div>
            @endforeach
            <button class="self-start rounded-lg bg-blue-700 px-4 py-2 text-white">{{ __('Bahoni saqlash') }}</button>
        </form>
        @foreach ($attempt->answers as $answer)
            @if (in_array($answer->question->type, ['long_answer', 'scenario', 'legal_case']))
            <div class="ai-assist-panel mt-4"><h3 class="text-sm font-semibold">{{ __('AI yordamchi tahlili:') }} {{ $answer->question->text }}</h3>
                @if ($answer->ai_guidance)<p class="mt-2 whitespace-pre-line text-sm">{{ $answer->ai_guidance }}</p>@endif
                <form method="POST" action="{{ route('admin.quizzes.guidance', $answer) }}" class="mt-3">@csrf<button class="ai-primary-button">{{ __('AI tahlili olish (yakuniy baho emas)') }}</button></form>
            </div>
            @endif
        @endforeach
    </article>
@empty <p>{{ __('Topshirilgan urinishlar yo‘q.') }}</p> @endforelse
{{ $attempts->links() }}
</div>
@endsection
