@extends('layouts.app')

@section('title', $course->title.__(' — Testlar'))

@section('content')
<h1 class="text-2xl font-semibold">{{ $course->title }} {{ __('— testlar va imtihonlar') }}</h1>

<form method="GET" class="mt-5 flex flex-wrap items-end gap-3"><label>{{ __('Savollar bankida qidirish') }}<input name="search" value="{{ request('search') }}" class="block rounded border p-2"></label><label>{{ __('Qiyinlik') }}<select name="difficulty" class="block rounded border p-2"><option value="">{{ __('Barchasi') }}</option>@foreach ([1,2,3] as $level)<option value="{{ $level }}" @selected(request('difficulty') == $level)>{{ $level }}</option>@endforeach</select></label><label>{{ __('Kategoriya') }}<select name="category_id" class="block rounded border p-2"><option value="">{{ __('Barchasi') }}</option>@foreach ($categories as $category)<option value="{{ $category->id }}" @selected(request('category_id') == $category->id)>{{ $category->name }}</option>@endforeach</select></label><button class="rounded bg-blue-700 px-4 py-2 text-white">{{ __('Filtrlash') }}</button></form>
<div class="mt-6 grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 edu-panel">
        <table class="edu-table">
            <thead><tr class="text-left text-[#0f1e3d]/60 dark:text-white/60 border-b"><th class="py-2">{{ __('Nomi') }}</th><th>{{ __('Tur') }}</th><th>{{ __('Savollar') }}</th><th>{{ __('Urinish') }}</th><th>{{ __('Vaqt') }}</th><th></th></tr></thead>
            <tbody>
                @forelse ($quizzes as $quiz)
                    <tr class="border-b last:border-0">
                        <td class="py-2 font-medium"><a class="text-blue-700 underline" href="{{ route('admin.quizzes.results', $quiz) }}">{{ $quiz->title }}</a></td>
                        <td>{{ $quiz->kind === 'exam' ? __('Imtihon') : __('Test') }}</td>
                        <td>{{ $quiz->questions->count() }}</td>
                        <td>{{ $quiz->max_attempts }}</td>
                        <td>{{ $quiz->time_limit_minutes ? $quiz->time_limit_minutes.' daq' : '—' }}</td>
                        <td class="text-right">
                            <form method="POST" action="{{ route('admin.courses.quizzes.destroy', $quiz) }}">@csrf @method('DELETE')
                                <button class="text-red-600 hover:underline">{{ __('O\'chir') }}</button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-[#0f1e3d]/50 dark:text-white/50">{{ __('Test yo\'q') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="edu-panel">
        <h2 class="font-semibold">{{ __('Yangi test/imtihon') }}</h2>
        <form method="POST" action="{{ route('admin.courses.quizzes.store', $course) }}" class="mt-4 space-y-3">
            @csrf
            <input name="title" placeholder="{{ __('Nomi') }}" required class="edu-input">
            <select name="kind" class="edu-input">
                <option value="quiz">{{ __('Test') }}</option><option value="exam">{{ __('Imtihon') }}</option>
            </select>
            <div class="grid grid-cols-2 gap-2">
                <input name="max_attempts" type="number" value="1" min="1" max="10" class="edu-input" placeholder="{{ __('Urinish') }}">
                <input name="pass_score_percent" type="number" value="60" min="1" max="100" class="edu-input" placeholder="{{ __('O\'tish %') }}">
                <input name="time_limit_minutes" type="number" min="1" class="edu-input" placeholder="{{ __('Vaqt (daq)') }}">
            </div>

            <label class="block text-sm">{{ __('Mavjud savollar banki') }}
                <select name="question_ids[]" multiple class="w-full rounded-lg border p-2">
                    @foreach ($bank as $question)<option value="{{ $question->id }}">{{ $question->text }}</option>@endforeach
                </select>
            </label>
            <label class="block text-sm">{{ __('Boshlanish') }}<input type="datetime-local" name="available_from" class="w-full rounded-lg border p-2"></label>
            <label class="block text-sm">{{ __('Tugash') }}<input type="datetime-local" name="available_until" class="w-full rounded-lg border p-2"></label>
            <label class="block text-sm"><input type="checkbox" name="shuffle_questions" value="1"> {{ __('Savollarni aralashtirish') }}</label>
            @if ($errors->any())<ul class="text-red-700">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
            <label class="block text-sm"><input name="shuffle_options" type="checkbox" value="1"> {{ __('Variantlarni aralashtirish') }}</label>
            <label class="block text-sm">{{ __('Tasodifiy tanlanadigan savollar soni') }}<input name="random_question_count" type="number" min="1" max="200" class="block w-full rounded border p-2"></label>
            <div id="q-list" class="space-y-4 border-t pt-3"></div>
            <button type="button" onclick="addQuestion()" class="edu-btn edu-btn-ghost w-full">{{ __('+ Savol qo\'shish') }}</button>

            <button class="edu-btn edu-btn-primary w-full">{{ __('Yaratish') }}</button>
        </form>
    </div>
</div>

<template id="question-category-options"><option value="">{{ __('Kurs kategoriyasi') }}</option>@foreach ($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</template>
<script>
let qi = 0;
function addQuestion() {
    const i = qi++;
    document.getElementById('q-list').insertAdjacentHTML('beforeend', `
        <div class="rounded-xl border border-[#0f1e3d]/10 p-3 space-y-2">
            <input name="questions[${i}][text]" placeholder="{{ __('Savol matni') }}" class="edu-input text-sm">
            <select name="questions[${i}][type]" class="rounded-lg border border-[#0f1e3d]/15 px-2 py-1 text-sm">
                <option value="single">{{ __('Bitta javob') }}</option><option value="multiple">{{ __('Bir nechta') }}</option>
                <option value="true_false">{{ __('To\'g\'ri/Xato') }}</option><option value="short_answer">{{ __('Qisqa javob') }}</option>
                <option value="matching">{{ __('Moslashtirish (JSON obyekt)') }}</option><option value="ordering">{{ __('Tartiblash (JSON massiv)') }}</option><option value="legal_case">{{ __('Huquqiy kazus (manual)') }}</option><option value="long_answer">{{ __('Keng javob (manual)') }}</option><option value="scenario">{{ __('Ssenariy (manual)') }}</option>
            </select>
            <label class="block text-xs">{{ __('Kategoriya') }}<select data-question-category name="questions[${i}][category_id]" class="block w-full rounded border p-2"></select></label>
            <label class="block text-xs">{{ __('Qiyinlik') }}<select name="questions[${i}][difficulty]" class="block w-full rounded border p-2"><option value="1">1</option><option value="2">2</option><option value="3">3</option></select></label>
            <label class="block text-xs">{{ __('Ball') }}
                <input name="questions[${i}][points]" type="number" min="1" max="100" value="1" required class="w-full rounded border px-2 py-1 text-sm">
            </label>
            <label class="block text-xs">{{ __('Kutilayotgan javob / o‘qituvchi izohi') }}
                <textarea name="questions[${i}][explanation]" rows="3" class="w-full rounded border px-2 py-1 text-sm"></textarea>
            </label>
            <p class="text-xs">{{ __('Qisqa javobda kutilayotgan matnni kiriting. Moslashtirishda {"a":"1","b":"2"}, tartiblashda ["a","b"] kabi JSON yozing. Javob formatini savol matnida ham tushuntiring.') }}</p>
            <div class="grid grid-cols-2 gap-2">
                <div><input name="questions[${i}][options][]" placeholder="A" class="w-full rounded border px-2 py-1 text-sm"><label class="text-xs"><input type="checkbox" name="questions[${i}][correct][]" value="0"> {{ __('to\'g\'ri') }}</label></div>
                <div><input name="questions[${i}][options][]" placeholder="B" class="w-full rounded border px-2 py-1 text-sm"><label class="text-xs"><input type="checkbox" name="questions[${i}][correct][]" value="1"> {{ __('to\'g\'ri') }}</label></div>
                <div><input name="questions[${i}][options][]" placeholder="C" class="w-full rounded border px-2 py-1 text-sm"><label class="text-xs"><input type="checkbox" name="questions[${i}][correct][]" value="2"> {{ __('to\'g\'ri') }}</label></div>
                <div><input name="questions[${i}][options][]" placeholder="D" class="w-full rounded border px-2 py-1 text-sm"><label class="text-xs"><input type="checkbox" name="questions[${i}][correct][]" value="3"> {{ __('to\'g\'ri') }}</label></div>
            </div>
        </div>`);
    document.getElementById('q-list').lastElementChild.querySelector('[data-question-category]').append(document.getElementById('question-category-options').content.cloneNode(true));
}
</script>
@endsection
