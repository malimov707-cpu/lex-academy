@extends('layouts.app')

@section('title', $lesson->title.__(' — Muharrir'))

@section('content')
<h1 class="text-2xl font-semibold">{{ __('Dars muharriri') }}</h1>
<p class="text-sm text-[#0f1e3d]/60 dark:text-white/60">{{ $lesson->module->course->title }} → {{ $lesson->module->title }} → {{ $lesson->title }} ({{ __($lesson->status) }})</p>

<div class="mt-6 grid gap-6 lg:grid-cols-3">
    <form method="POST" action="{{ route('admin.lessons.update', $lesson) }}" class="lg:col-span-2 edu-panel space-y-4">
        @csrf @method('PUT')
        <div>
            <label class="block text-sm font-medium">{{ __('Dars nomi') }}</label>
            <input name="title" value="{{ $lesson->title }}" required class="mt-1 edu-input">
        </div>
        <div>
            <label class="block text-sm font-medium">{{ __('Tavsif') }}</label>
            <textarea name="description" rows="2" class="mt-1 edu-input">{{ $lesson->description }}</textarea>
        </div>
        <div>
            <label class="block text-sm font-medium">{{ __('Asosiy matn') }}</label>
            <textarea name="body" rows="10" class="mt-1 w-full rounded-lg border border-[#0f1e3d]/15 px-3 py-2 font-serif">{{ $lesson->body }}</textarea>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="block text-sm font-medium">{{ __('O\'quv maqsadlari (har biri yangi qator)') }}</label>
                <textarea name="objectives" rows="4" class="mt-1 edu-input">{{ implode("\n", $lesson->objectives ?? []) }}</textarea>
            </div>
            <div>
                <label class="block text-sm font-medium">{{ __('Completion mezonlari (yangi qator)') }}</label>
                <textarea name="completion_criteria" rows="4" class="mt-1 edu-input">{{ implode("\n", $lesson->completion_criteria ?? []) }}</textarea>
            </div>
        </div>
        <fieldset class="flex flex-col gap-3 rounded-xl bg-blue-50 p-4"><legend class="font-semibold">{{ __('Avtomatik tugatish mezonlari') }}</legend><input type="hidden" name="completion_rules_present" value="1"><label><input type="checkbox" name="require_interactive" value="1" @checked($lesson->completion_rules['require_interactive'] ?? false)> {{ __('Barcha interaktiv savollar to‘g‘ri bajarilsin') }}</label>
<label>{{ __('Majburiy test') }}<select name="required_quiz_id" class="block w-full rounded border p-2"><option value="">{{ __('Tanlanmagan') }}</option>@foreach ($quizzes as $quiz)<option value="{{ $quiz->id }}" @selected(($lesson->completion_rules['quiz_id'] ?? null) == $quiz->id)>{{ $quiz->title }}</option>@endforeach</select></label><label>{{ __('Majburiy topshiriq') }}<select name="required_assignment_id" class="block w-full rounded border p-2"><option value="">{{ __('Tanlanmagan') }}</option>@foreach ($assignments as $assignment)<option value="{{ $assignment->id }}" @selected(($lesson->completion_rules['assignment_id'] ?? null) == $assignment->id)>{{ $assignment->title }}</option>@endforeach</select></label></fieldset>
        <button class="edu-btn edu-btn-primary">{{ __('Saqlash') }}</button>
    </form>

    <div class="space-y-4">
        <div class="edu-panel">
            <h2 class="font-semibold">{{ __('Materiallar') }}</h2>
            <ul class="mt-3 space-y-2 text-sm">
                @forelse ($lesson->materials as $material)
                    <li class="flex justify-between"><span>{{ $material->title }}</span><span class="text-[#0f1e3d]/50 dark:text-white/50">{{ __($material->type) }}</span></li>
                @empty
                    <li class="text-[#0f1e3d]/50 dark:text-white/50">{{ __('Hali material yo\'q') }}</li>
                @endforelse
            </ul>
            <form method="POST" action="{{ route('admin.lessons.materials.store', $lesson) }}" enctype="multipart/form-data" class="mt-4 space-y-3">
                @csrf
                <input name="title" placeholder="{{ __('Material nomi') }}" required class="edu-input">
                <select name="type" class="edu-input">
                    <option value="video">{{ __('Video') }}</option><option value="audio">{{ __('Audio') }}</option><option value="pdf">PDF</option>
                    <option value="docx">DOCX</option><option value="image">{{ __('Rasm') }}</option><option value="link">{{ __('Havola') }}</option><option value="legal_doc">{{ __('Huquqiy hujjat') }}</option>
                </select>
                <input type="file" name="file" class="w-full text-sm">
                <input name="external_url" placeholder="{{ __('yoki havola (URL)') }}" class="edu-input">
                <button class="edu-btn edu-btn-ghost w-full">{{ __('Qo\'shish') }}</button>
            </form>
        </div>

        @if ($lesson->status !== 'published')
            <form method="POST" action="{{ route('admin.lessons.publish', $lesson) }}" class="rounded-2xl border border-emerald-200 bg-emerald-50 p-6">
                @csrf @method('PATCH')
                <button class="w-full rounded-lg bg-emerald-600 px-4 py-2 text-white">{{ __('Darsni nashr qilish') }}</button>
            </form>
        @endif
    </div>
</div>
<section class="mt-6 rounded-2xl bg-white p-6"><h2 class="font-semibold">{{ __('Versiyalar tarixi') }}</h2>
@forelse ($lesson->versions as $version)<details class="mt-3"><summary>{{ $version->created_at->format('d.m.Y H:i') }} · {{ $version->snapshot['title'] }}</summary><p class="mt-2 whitespace-pre-line">{{ $version->snapshot['body'] }}</p>@foreach ($version->snapshot['blocks'] ?? [] as $oldBlock)<div class="mt-2 rounded border p-3"><strong>{{ __($blockTypes[$oldBlock['type']] ?? $oldBlock['type']) }}</strong><p class="whitespace-pre-line">{{ $oldBlock['content']['text'] ?? '' }}</p></div>@endforeach</details>@empty<p class="mt-2 text-sm">{{ __('Hali o‘zgartirishlar yo‘q.') }}</p>@endforelse
</section>
<section class="mt-6 rounded-2xl bg-slate-50 p-6">
<div class="flex flex-wrap items-center justify-between gap-3"><h2 class="text-xl font-semibold">{{ __('Blokli dars muharriri') }}</h2><a href="{{ route('admin.lessons.preview', $lesson) }}" class="text-blue-700 underline">{{ __('Oldindan ko‘rish') }}</a></div>
@if ($errors->any())<ul role="alert" class="mt-4 text-red-700">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
<form method="POST" action="{{ route('admin.lessons.blocks.update', $lesson) }}" data-block-editor class="mt-5 flex flex-col gap-4">@csrf @method('PUT')<input type="hidden" name="blocks_present" value="1">
<div data-block-list class="flex flex-col gap-4">@foreach ($lesson->blocks as $index => $block) @include('admin.courses.block-fields') @endforeach</div>
<p data-block-empty class="text-sm">{{ __('Bloklarni qo‘shing, sichqoncha yoki yuqori/past tugmalari bilan tartiblang va saqlang.') }}</p>
<div class="flex flex-wrap gap-3"><button type="button" data-block-add class="rounded border px-4 py-2">{{ __('Blok qo‘shish') }}</button><button class="rounded bg-blue-700 px-4 py-2 text-white">{{ __('Bloklarni saqlash') }}</button></div>
</form>
<template data-block-template>@include('admin.courses.block-fields', ['block' => null, 'index' => '__INDEX__'])</template>
</section>
@endsection
