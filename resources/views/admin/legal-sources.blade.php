@extends('layouts.app')
@section('title', __('Huquqiy manbalar'))
@section('content')
<h1 class="text-2xl font-semibold">{{ __('Huquqiy manbalar') }}</h1><p class="mt-3">{{ __('AI faqat tasdiqlangan manba parchalaridan foydalanadi. Manbaning dolzarbligi inson tomonidan tekshiriladi.') }}</p>
@if ($errors->any())<p class="mt-4 text-red-700" role="alert">{{ $errors->first() }}</p>@endif
<div class="mt-6 grid items-start gap-6 lg:grid-cols-3"><div class="flex flex-col gap-4 lg:col-span-2">
@forelse ($sources as $source)
    <article class="rounded-2xl bg-white p-6">
        <h2 class="font-semibold">{{ $source->title }}</h2>
        <p>{{ $source->course?->title ?? __('Umumiy manba') }} · {{ __($source->status) }} · {{ $source->topic ?? __('Mavzu tanlanmagan') }}</p>
        <a href="{{ $source->url }}" rel="noopener noreferrer" target="_blank" class="text-blue-700 underline">{{ $source->url }}</a>
        <p class="mt-3 whitespace-pre-line">{{ $source->excerpt }}</p>
        @if (auth()->user()->isAdmin())
            <form method="POST" action="{{ route('admin.legal-sources.update', $source) }}" class="mt-4 flex flex-wrap items-center gap-3">
                @csrf @method('PATCH')
                <label><input type="checkbox" name="confirmed" value="1" required> {{ __('Manbani tekshirdim') }}</label>
                <select name="status" aria-label="{{ __('Holat') }}" class="rounded border p-2">
                    <option value="verified" @selected($source->status === 'verified')>{{ __('Tasdiqlash') }}</option>
                    <option value="archived" @selected($source->status === 'archived')>{{ __('Arxiv') }}</option>
                </select>
                <label class="flex items-center gap-2 text-sm">
                    <span>{{ __('Bildirishnoma mavzusi') }}</span>
                    <select name="topic" class="rounded border p-2">
                        <option value="">{{ __('Tanlanmagan') }}</option>
                        @foreach ($topics as $topic)
                            <option value="{{ $topic }}" @selected($source->topic === $topic)>{{ $topic }}</option>
                        @endforeach
                    </select>
                </label>
                <button class="rounded bg-blue-700 px-4 py-2 text-white">{{ __('Saqlash') }}</button>
            </form>
        @endif
    </article>
@empty
    <p>{{ __('Hozircha manba yo‘q.') }}</p>
@endforelse
{{ $sources->links() }}</div>
<form method="POST" action="{{ route('admin.legal-sources.store') }}" class="flex flex-col gap-3 rounded-2xl bg-white p-6">
    @csrf
    <label>{{ __('Kurs') }}<select name="course_id" class="block w-full rounded border p-2">@if (auth()->user()->isAdmin())<option value="">{{ __('Umumiy manba') }}</option>@endif @foreach ($courses as $course)<option value="{{ $course->id }}">{{ $course->title }}</option>@endforeach</select></label>
    <label>{{ __('Sarlavha') }}<input name="title" required class="block w-full rounded border p-2"></label>
    <label>{{ __('Rasmiy manba havolasi') }}<input type="url" name="url" required class="block w-full rounded border p-2"></label>
    <label>{{ __('Bildirishnoma mavzusi') }}<select name="topic" class="block w-full rounded border p-2"><option value="">{{ __('Tanlanmagan') }}</option>@foreach ($topics as $topic)<option value="{{ $topic }}">{{ $topic }}</option>@endforeach</select></label>
    <label>{{ __('Tegishli parcha') }}<textarea name="excerpt" rows="8" required maxlength="20000" class="block w-full rounded border p-2"></textarea></label>
    <button class="rounded bg-blue-700 p-2 text-white">{{ __('Qoralama sifatida saqlash') }}</button>
</form></div>
@endsection
