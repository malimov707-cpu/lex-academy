@extends('layouts.ai')
@section('title', __('AI Ustoz'))
@section('content')
<div class="ai-context-bar"><a href="{{ route('student.lesson', [$course, $lesson]) }}">← {{ __('Darsga qaytish') }}</a><span>{{ $course->title }} <span aria-hidden="true">/</span> {{ $lesson->title }}</span><label class="ai-mode-label" for="mode">{{ __('Rejim') }}<select id="mode">@foreach ($modes as $key => $label)<option value="{{ $key }}" @selected($session->mode === $key)>{{ __($label) }}</option>@endforeach</select></label></div>
<div data-ai-chat data-endpoint="{{ route('student.tutor.send', [$course, $lesson]) }}" data-pending="{{ __('Javob kutilmoqda…') }}" data-error="{{ __('Ulanish uzildi. Matningiz saqlandi, qayta urinib ko‘ring.') }}" data-confidence="{{ __('ishonch:') }}" data-user-name="{{ auth()->user()->name }}" data-assistant-name="{{ __('AI Ustoz') }}">
    <div data-ai-welcome @if ($session->messages->isNotEmpty()) hidden @endif><x-ai-welcome /></div>
    <div id="messages" class="ai-messages" role="log" aria-label="{{ __('AI suhbat tarixi') }}" aria-live="polite" @if ($session->messages->isEmpty()) hidden @endif>
        @foreach ($session->messages as $message)<article class="ai-message {{ $message->role === 'user' ? 'ai-message-user' : 'ai-message-assistant' }}"><span class="ai-message-author">{{ $message->role === 'user' ? auth()->user()->name : __('AI Ustoz') }}</span><p>{{ $message->content }}</p>@if ($message->confidence !== null)<small>{{ __('ishonch:') }} {{ round($message->confidence * 100) }}%</small>@endif</article>@endforeach
    </div>
    <div class="ai-suggestions">
        @foreach ([['Darsni tushuntirish', 'Shu darsni sodda tilda tushuntiring.', 'explain', 'lesson'], ['Amaliy mashqlar', 'Shu mavzu bo‘yicha amaliy mashq bering.', 'practice', 'assignments'], ['Asosiy tushunchalar', 'Darsdagi asosiy tushunchalarni qisqacha takrorlang.', 'revision', 'sources'], ['Bilimni tekshirish', 'Shu mavzu bo‘yicha bilimimni savollar bilan tekshiring.', 'quiz_mode', 'quiz']] as [$label, $prompt, $mode, $icon])
        <button type="button" class="ai-suggestion" data-ai-prompt="{{ __($prompt) }}" data-ai-mode="{{ $mode }}"><span class="ai-suggestion-icon"><x-nav-icon :name="$icon" /></span><strong>{{ __($label) }}</strong><span class="ai-suggestion-arrow" aria-hidden="true">↗</span></button>
        @endforeach
    </div>
    <form id="tutor-form" class="ai-composer" data-ai-form>@csrf
        <label for="message" class="sr-only">{{ __('Savolingiz') }}</label><textarea id="message" name="message" required maxlength="4000" rows="3" placeholder="{{ __('Savolingizni shu yerga yozing…') }}"></textarea>
        <div class="ai-composer-footer"><span><span class="ai-online-dot" aria-hidden="true"></span>{{ __('Dars kontekstiga mos yordam') }}</span><button type="submit" class="ai-send" aria-label="{{ __('Yuborish') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 19V5m-6 6 6-6 6 6"/></svg></button></div>
    </form>
    <p class="ai-disclaimer">{{ __('AI xato qilishi mumkin. Huquqiy manbalarni tekshiring.') }}</p>
    <p class="ai-chat-status" role="status" data-ai-status></p>
</div>
@endsection
