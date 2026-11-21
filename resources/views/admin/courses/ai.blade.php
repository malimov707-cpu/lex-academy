@extends('layouts.ai')
@section('title', __('O‘qituvchi AI vositalari'))
@section('content')
<div class="ai-context-bar"><a href="{{ route('ai.index') }}">← {{ __('AI Ustoz') }}</a><span>{{ $course->title }}</span></div>
<x-ai-welcome :title="__('O‘qituvchi AI vositalari')" :description="__('Darslaringiz uchun g‘oyalar, mashqlar va manbali qoralamalar tayyorlang.')" compact />
<div class="ai-suggestions">
@foreach (['plan' => 'Dars rejasi', 'quiz' => 'Test savollari', 'case' => 'Kazuslar', 'summary' => 'Dars xulosasi'] as $kind => $label)<button type="button" class="ai-suggestion" data-ai-kind="{{ $kind }}"><span class="ai-suggestion-icon"><x-nav-icon name="tutor" /></span><strong>{{ __($label) }}</strong><span class="ai-suggestion-arrow" aria-hidden="true">↗</span></button>@endforeach
</div>
@if ($errors->any())<ul role="alert" class="mt-4 text-red-700">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
<div class="mt-7 grid items-start gap-6 lg:grid-cols-3">
<form method="POST" action="{{ route('admin.teacher-ai.store', $course) }}" class="ai-teacher-form flex flex-col gap-4" data-ai-teacher-form>@csrf
<h2 class="font-semibold">{{ __('Yangi qoralama') }}</h2>
<label>{{ __('Material turi') }}<select name="kind" class="ai-field">@foreach ($kinds as $value => $label)<option value="{{ $value }}" @selected(old('kind') === $value)>{{ __($label) }}</option>@endforeach</select></label>
<label>{{ __('Mavzu') }}<input name="topic" required maxlength="255" value="{{ old('topic') }}" class="ai-field"></label>
<label>{{ __('Tekshirilgan manbalar va tegishli parchalar') }}<textarea name="sources" required maxlength="8000" rows="6" class="ai-field">{{ old('sources') }}</textarea></label>
<label>{{ __('Qo‘shimcha ko‘rsatmalar') }}<textarea name="instructions" maxlength="2000" rows="3" class="ai-field">{{ old('instructions') }}</textarea></label>
<button class="ai-primary-button">{{ __('Qoralama yaratish') }}</button>
<p class="text-sm">{{ __('AI manbalarni mustaqil tekshirmaydi. Materialni nashrdan oldin ko‘rib chiqing.') }}</p>
</form>
<div class="flex flex-col gap-4 lg:col-span-2">
@forelse ($drafts as $draft)
<article class="ai-result-card"><h2 class="font-semibold">{{ $draft->topic }} · {{ __($kinds[$draft->kind]) }}</h2>
<p class="mt-2 text-sm">{{ $draft->status === 'approved' ? __('Tasdiqlangan') : __('Tasdiqlanmagan qoralama') }}</p>
@if ($draft->status === 'draft')
<form method="POST" action="{{ route('admin.teacher-ai.approve', $draft) }}" class="mt-4 flex flex-col gap-3">@csrf @method('PATCH')
<label>{{ __('Tahrirlanadigan material') }}<textarea name="content" required maxlength="50000" rows="12" class="ai-field">{{ $draft->content }}</textarea></label>
<p class="whitespace-pre-line text-sm">{{ $draft->sources }}</p>
<label><input type="checkbox" name="sources_verified" value="1" required> {{ __('Huquqiy manbalar va mazmunni tekshirdim') }}</label>
<label>{{ __('Darsga qo‘shish') }}<select name="lesson_id" required class="ai-field">@foreach ($lessons as $lesson)<option value="{{ $lesson->id }}">{{ $lesson->title }}</option>@endforeach</select></label>
<button class="self-start rounded bg-emerald-700 px-4 py-2 text-white">{{ __('Tasdiqlash va darsga qo‘shish') }}</button>
</form>
@else<p class="mt-4 whitespace-pre-line">{{ $draft->content }}</p>@endif
</article>
@empty<p>{{ __('Hozircha AI qoralamasi yo‘q.') }}</p>@endforelse
{{ $drafts->links() }}
</div></div>
@endsection
